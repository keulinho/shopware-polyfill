# Shopware polyfill

Experimental forward-compatibility polyfills for Shopware extensions that want to adopt selected replacement APIs while still supporting older Shopware versions.

This repository is a draft. It has no stable release or backward-compatibility promise yet.

## Scope

This package removes PHP and Symfony integration barriers that otherwise prevent one plugin codebase from naming a replacement API across supported Shopware versions. Its intended building blocks are:

- identity-preserving PHP class aliases and matching dependency-injection aliases;
- conditional declarations for replacement interfaces, abstract classes, and related value types that do not exist on older versions;
- provider-side inheritance bridges where a replacement abstract class can also satisfy the legacy interface without changing its meaning.

Ordinary method migrations do not normally require a package-level compatibility trait. A plugin can declare the old and new methods in parallel: older Shopware calls the legacy method, newer Shopware calls the replacement, and an additional legacy method remains valid after the parent method is removed. The package supplies a missing parent class or interface when PHP would otherwise fail to load that plugin class, but the plugin owns the two method implementations and any shared internal logic.

The project therefore does not generally automate behavioral forwarding with traits. Such forwarding often requires plugin-specific decisions or unavailable information, such as choosing a locale, mapping a number-range type to an ID, or translating response semantics. A trait is only a candidate when the conversion is universal, lossless, and carries no behavioral policy.

The package also does not make an existing older core object an instance of a newly introduced abstract class, add methods to loaded core classes, or make old core dispatch new events. Those cases require an explicit consumer adapter or parallel old/new integration inside the plugin.

## Installation

```bash
composer require keulinho/shopware-polyfill:dev-main
```

Register the bundle from every plugin that requires the package:

```php
use Keulinho\ShopwarePolyfill\ShopwarePolyfillBundle;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;

public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
{
    return [
        ...parent::getAdditionalBundles($parameters),
        new ShopwarePolyfillBundle(),
    ];
}
```

Shopware deduplicates additional bundles by bundle name. If several active plugins return `ShopwarePolyfillBundle`, Symfony registers it only once.

The bundle is needed for dependency-injection aliases. PHP class aliases, replacement abstract classes, and replacement interfaces are loaded automatically through Composer.

## Moved classes

On a Shopware version where the canonical class does not exist but the legacy class does, the package creates the canonical name as a `class_alias()` of the legacy class:

These mappings mirror the class moves proposed in [shopware/shopware#20525](https://github.com/shopware/shopware/pull/20525).

| Legacy class | Canonical class |
|---|---|
| `Shopware\Administration\Controller\NotificationController` | `Shopware\Core\Framework\Notification\Api\NotificationController` |
| `Shopware\Administration\Notification\NotificationCollection` | `Shopware\Core\Framework\Notification\NotificationCollection` |
| `Shopware\Administration\Notification\NotificationDefinition` | `Shopware\Core\Framework\Notification\NotificationDefinition` |
| `Shopware\Administration\Notification\NotificationEntity` | `Shopware\Core\Framework\Notification\NotificationEntity` |
| `Shopware\Elasticsearch\Product\SearchConfigLoader` | `Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader` |

The bundle also exposes the canonical service IDs for the moved notification controller, notification definition, and search config loader when only their legacy service IDs are registered. Existing canonical definitions always win.

## Interface migrations

The package covers three interface-related migrations. They intentionally solve only missing-type and provider-compatibility problems; they do not replace older core services or make older core code call APIs it does not know.

### ProductStreamBuilderInterface to AbstractProductStreamBuilder

The package conditionally provides `Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder` when it is absent and the deprecated `ProductStreamBuilderInterface` exists.

The polyfilled abstract class implements the predecessor interface and provides `buildFilters()` by creating a `Criteria`, calling `enrichCriteria()`, and returning its filters. A plugin provider therefore implements the replacement API:

```php
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class PluginProductStreamBuilder extends AbstractProductStreamBuilder
{
    public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
    {
        // Add the plugin's filters and criteria state.
    }
}
```

When the package supplies the abstract class, older Shopware sees this provider through `ProductStreamBuilderInterface`; newer Shopware uses `AbstractProductStreamBuilder`. Criteria state added by the new API cannot be represented by the legacy `buildFilters()` return type, so only filters are carried back to old core.

The provider bridge does not make an existing older Shopware core `ProductStreamBuilder` an instance of the new abstract class. Consumers that receive the core service must accept both generations.

#### Consuming product stream builders across versions

A consumer can accept both the replacement abstract class and the deprecated interface:

```php
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class ProductStreamConsumer
{
    public function __construct(
        private readonly AbstractProductStreamBuilder|ProductStreamBuilderInterface $builder,
    ) {
    }

    public function enrichCriteria(Criteria $criteria, string $streamId, Context $context): void
    {
        if ($this->builder instanceof AbstractProductStreamBuilder) {
            $this->builder->enrichCriteria($criteria, $streamId, $context);

            return;
        }

        $criteria->addFilter(...$this->builder->buildFilters($streamId, $context));
    }
}
```

Check for `AbstractProductStreamBuilder` first because a new implementation may also satisfy the deprecated interface. PHP accepts a union containing a class or interface that no longer exists, so this remains valid after `ProductStreamBuilderInterface` is removed: an object matching `AbstractProductStreamBuilder` satisfies the union and an `instanceof ProductStreamBuilderInterface` check evaluates to `false`.

Wire the constructor argument explicitly to the concrete product stream builder service, or to a decorator's `.inner` service. Symfony cannot reliably choose a service from this union through autowiring alone. Static analysers running only against a Shopware version where the interface has already been removed may additionally need a compatibility stub or configuration that knows the legacy symbol.

`AbstractProductStreamBuilder` became native in Shopware 6.7.12. Composer cannot modify the native class after it exists, so its inheritance relationship is determined by the installed Shopware patch release. The compatibility bridge is proposed in [shopware/shopware#20607](https://github.com/shopware/shopware/pull/20607); until that change is present, the native abstract class does not implement its predecessor interface.

### NumberRangeValueGeneratorInterface to AbstractNumberRangeValueGenerator

The package conditionally provides `Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator` when it is absent and the deprecated `NumberRangeValueGeneratorInterface` exists. The polyfilled abstract class implements the predecessor interface, so plugin providers can extend the replacement type on older Shopware versions.

The replacement introduces `previewPatternByNumberRangeId()`, but the old interface still requires `previewPattern()`. Its type-based input cannot be translated generically into a number-range ID. Cross-version providers must therefore implement both methods in parallel:

```php
public function previewPattern(string $definition, ?string $pattern, int $start): string
{
    // Keep the existing legacy implementation for Shopware versions that call it.
}

public function previewPatternByNumberRangeId(
    string $numberRangeId,
    ?string $pattern = null,
    ?int $start = null,
): string {
    // Implement the replacement operation used by newer Shopware versions.
}
```

`AbstractNumberRangeValueGenerator` became native in Shopware 6.7.12. As with the product-stream bridge, the package cannot change the inheritance of that native class. An equivalent core change making it implement the deprecated interface is needed for seamless provider compatibility throughout 6.7.

Existing older core implementations do not become instances of the replacement abstract class. Consumers must accept both types or use a case-specific adapter when they require one uniform abstraction.

### DynamicallyScheduledTaskHandler

`Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler` became native in Shopware 6.7.13. The package conditionally declares the same interface on older versions, allowing one handler class to implement it across the supported version range.

Keep the existing `rescheduleTask()` override for older Shopware versions and add `getNextExecutionTime()` in parallel:

```php
use Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

final class PluginScheduledTaskHandler extends ScheduledTaskHandler implements DynamicallyScheduledTaskHandler
{
    // Keep the existing rescheduleTask() override unchanged.

    public function getNextExecutionTime(
        ScheduledTask $task,
        ScheduledTaskEntity $taskEntity,
    ): ?\DateTimeInterface {
        return $this->calculateNextExecutionTime($task, $taskEntity);
    }
}
```

Older Shopware does not know the interface and continues to invoke `rescheduleTask()`. Shopware 6.7.13 and newer check the interface first, call `getNextExecutionTime()`, and return without invoking the legacy override. Declaring both methods therefore does not trigger the runtime deprecation in a correctly registered `messenger.message_handler` service.

The polyfill intentionally does not include a trait: the old method remains the plugin's existing persistence implementation, while the new method only calculates and returns the next execution time.

## Supported versions

The current draft targets Shopware 6.6, 6.7, and 6.8. Every polyfill is conditional and becomes a no-op when Shopware provides the native API.

## Testing

```bash
composer test
```
