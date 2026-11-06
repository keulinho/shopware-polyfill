# Shopware polyfill

Experimental forward-compatibility polyfills for Shopware extensions that want to adopt selected replacement APIs while still supporting older Shopware versions.

This repository is a draft. It has no stable release or backward-compatibility promise yet.

The [revised RFC](docs/rfc.md) explains the proposed scope, current compatibility gaps, and open contract questions.

## Scope

This package removes PHP and Symfony integration barriers that otherwise prevent one plugin codebase from naming a replacement API across supported Shopware versions. Its intended building blocks are:

- identity-preserving PHP class aliases and matching dependency-injection aliases;
- conditional declarations for replacement interfaces, abstract classes, and related value types that do not exist on older versions;
- provider-side inheritance bridges where a replacement abstract class can also satisfy the legacy interface without changing its meaning.
- extension-facing marker attribute declarations, with the native definitions taking precedence.

Ordinary method migrations do not normally require a package-level compatibility trait. A plugin can declare the old and new methods in parallel: older Shopware calls the legacy method, newer Shopware calls the replacement, and an additional legacy method remains valid after the parent method is removed. The package supplies a missing parent class or interface when PHP would otherwise fail to load that plugin class, but the plugin owns the two method implementations and any shared internal logic.

The project therefore does not generally automate behavioral forwarding with traits. Such forwarding often requires plugin-specific decisions or unavailable information, such as choosing a locale, mapping a number-range type to an ID, or translating response semantics. A trait is only a candidate when the conversion is universal, lossless, and carries no behavioral policy.

The package also does not make an existing older core object an instance of a newly introduced abstract class, add methods to loaded core classes, or make old core dispatch new events. Those cases require an explicit consumer adapter or parallel old/new integration inside the plugin.

## Installation

```bash
composer require keulinho/shopware-polyfill:dev-main
```

Register the bundle from every plugin that needs service aliases or HTTP route polyfills:

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

The bundle is needed for dependency-injection aliases.
PHP class aliases, replacement abstract classes, replacement interfaces, and metadata attributes are loaded automatically through Composer.

The bundle also registers route polyfills. It must therefore be registered even when a plugin only uses one of the HTTP route compatibility layers described below.

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

`AbstractProductStreamBuilder` became native in Shopware 6.7.12.
Composer cannot modify the native class after it exists, so its inheritance relationship is determined by the installed Shopware patch release.
The compatibility bridge from [shopware/shopware#20607](https://github.com/shopware/shopware/pull/20607) is merged and first present in 6.7.16.0.
Native versions from 6.7.12 through 6.7.15 lack that bridge; the polyfill cannot repair their inheritance.

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

## Extension marker attributes

The package conditionally supplies [`Shopware\Core\Framework\Webhook\NotHookable`](https://github.com/shopware/shopware/blob/v6.7.14.1/src/Core/Framework/Webhook/NotHookable.php), introduced in 6.7.14.1, and `Shopware\Core\Framework\Event\SensitiveMail` from the merged, unreleased native implementation.
`NotHookable` marks extension flow events that should be excluded from webhook delivery.
`SensitiveMail` marks mail events whose logs should contain metadata only.
Both are class-level markers without constructor arguments.
Composer loads them without bundle registration, and native definitions always win.

```php
use Shopware\Core\Framework\Webhook\NotHookable;
use Shopware\Core\Framework\Event\FlowEventAware;

#[NotHookable]
final class PluginFlowEvent implements FlowEventAware
{
    // Existing event implementation.
}
```

This is a declaration polyfill only.
Older core without `NotHookablePolicy` ignores the marker and can still deliver the event to webhooks.
An extension that requires webhook exclusion must require a core version with the policy or provide its own tested enforcement on older versions.
The attribute class alone is not a security fix.
PHP already permits unresolved attribute names on declarations; the polyfill additionally makes class lookup and reflection instantiation work.

### Attribute review since 6.7.0.0

| Attribute | First available | Assessment |
|---|---|---|
| `NotHookable` | 6.7.14.1 | Extension-facing marker; declaration supplied, webhook policy not backported. |
| `SensitiveMail` | Merged on trunk; absent from published 6.7 tags through 6.7.16.0 | Declaration supplied from the merged implementation; mail-log policy not backported. |
| `IsFlowEventAware` | Already in 6.7.0.0 | Outside the requested set of newer attributes. |
| DAL `ReverseInherited` | 6.7.7.0 | Requires attribute entity compiler support. |
| DAL `SearchRanking`, `Password`, `ListField` | 6.7.9.0 | Requires ranking, serialization, or field-type support. |
| MCP `McpToolRequires`, `McpToolDependsOn` | 6.7.11.0 | Designed for tools, including extension tools, but depends on the experimental MCP integration. |
| MCP `McpToolGroup` | 6.7.14.0 | Extension tool metadata; older MCP versions do not apply grouping. |
| BC-change attributes | 6.7.14.0 onward | Internal core planning metadata, not intended for extension use; excluded. |

`SensitiveMail` is documented for extension events implementing `MailAware` in the [upcoming release notes](https://github.com/shopware/shopware/blob/trunk/RELEASE_INFO-6.7.md).
Its consumer carries sensitivity through stored flows and mail logging.
Supplying only the marker on older core does not redact logs or template sample data.
This experimental polyfill matches [the merged implementation](https://github.com/shopware/shopware/blob/e41e3f38b5d93b56534a8a8196bff32abd2250d8/src/Core/Framework/Event/SensitiveMail.php); its upstream contract may still change before release.

```php
use Shopware\Core\Framework\Event\SensitiveMail;
use Shopware\Core\Framework\Event\MailAware;

#[SensitiveMail]
final class PluginMailEvent implements MailAware
{
    // Existing mail event implementation.
}
```

### MCP declarations as a follow-up

It is reasonable to conditionally supply Shopware's three MCP attributes and declaration-only interfaces whose method types exist on older core.
This would allow extensions to carry MCP declarations on versions where no MCP consumer runs.
A tool service tagged `shopware.mcp.tool` is not executed merely because the tag exists.
An unused private service can be removed by Symfony when no MCP integration references it.

This does not make arbitrary MCP services safe to register unconditionally:

- Missing parent classes or implemented interfaces prevent PHP from loading a tool class.
- A polyfilled `McpContextProviderInterface` does not create a context-provider service for constructor injection.
- The usual `McpToolResponse` parent is executable code, not metadata; it needs a separate compatibility design.
- `Mcp\Capability\Attribute\McpTool` and related SDK types belong to `mcp/sdk`, not Shopware.
  Use the real SDK dependency when those types must be instantiated; this package should not copy SDK contracts.
- Partial native MCP implementations still consume the declarations they know, so missing dependency or grouping support cannot always be treated as inert.

The target is one extension implementation with inactive MCP integration on older core.
It needs class-loading and DI-compilation tests on that older core, plus native discovery and invocation tests on newer core.
These MCP polyfills are not implemented in this draft yet.
`McpToolRequires` documents required privileges; it does not enforce them.
Existing DAL attributes also gained options during 6.7; a polyfill cannot add arguments to an already defined native constructor.

## HTTP routes

The bundle backports selected route declarations when the installed Shopware version does not provide them yet. It always routes to the existing Shopware controller service, preserving decorators and the existing request and response behavior.

### Additional HTTP methods

The following methods are added to the existing routes:

| Method | Path |
|---|---|
| `PATCH` | `/api/_info/config-me` |
| `GET` | `/store-api/media` |
| `GET` | `/store-api/product/{productId}/cross-selling` |
| `GET` | `/store-api/product/{productId}` |
| `GET` | `/store-api/product/{productId}/find-variant` |
| `GET` | `/store-api/product-listing/{categoryId}` |
| `GET` | `/store-api/product/{productId}/reviews` |
| `GET` | `/store-api/search` |
| `GET` | `/store-api/search-suggest` |
| `GET` | `/store-api/landing-page/{landingPageId}` |

The polyfill reuses the native route name, controller, scope, authentication, entity, requirements, and options. If the installed Shopware version already allows the method, no overwrite is registered.

The Store API declarations only backport support for making the request with `GET`. They do not backport the HTTP caching infrastructure or caching behavior introduced alongside those methods in later Shopware versions.

### Storefront path aliases

On versions that still expose the Storefront header and footer under `/header` and `/footer`, the bundle additionally accepts the newer paths:

| New path | Existing controller |
|---|---|
| `/_esi/global/header` | `NavigationController::header()` |
| `/_esi/global/footer` | `NavigationController::footer()` |

The aliases copy the native route's methods, defaults, requirements, and options. They are not registered once Shopware provides the new paths itself.

Route polyfills are limited to declarations where the existing controller accepts the same input and returns the same response. Renames that require payload conversion, different identifiers, new permissions, or different response semantics are intentionally not included.

## Supported versions

The current draft targets Shopware 6.6, 6.7, and 6.8. Every polyfill is conditional and becomes a no-op when Shopware provides the native API.

## Testing

```bash
composer test
```

The marker regression tests cover reflection, class targets, and bootstrap idempotence.
Run them with and without native `NotHookable` and `SensitiveMail` definitions to check native precedence.
Source-snapshot reflection checks do not replace full Shopware integration testing across the supported versions.
