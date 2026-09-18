# Shopware polyfill

Experimental forward-compatibility polyfills for Shopware extensions that want to adopt selected replacement APIs while still supporting older Shopware versions.

This repository is a draft. It has no stable release or backward-compatibility promise yet.

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

The bundle is needed for dependency-injection aliases. PHP class aliases and replacement abstract classes are loaded automatically through Composer.

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

## Replacement abstract classes

The package conditionally provides these types when they are absent:

- `Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder`
- `Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator`

Both polyfilled abstract classes implement their deprecated predecessor interface. This lets a plugin provide one implementation that extends the replacement abstract class while remaining acceptable to older Shopware code expecting the interface.

For product streams, the inherited `buildFilters()` compatibility method creates a `Criteria`, calls `enrichCriteria()`, and returns its filters. Criteria state added by the new API cannot be represented by the legacy return type.

The number-range replacement adds a genuinely new operation, `previewPatternByNumberRangeId()`. Implementations extending the polyfilled abstract class must provide that operation themselves.

These are provider-side bridges. They do not make an existing older Shopware core implementation an instance of a newly introduced abstract class. Consumers that inject the replacement abstract type while running an older core implementation still need a case-specific adapter.

`AbstractNumberRangeValueGenerator` became native in Shopware 6.7.12 and `AbstractProductStreamBuilder` in 6.7.13. Composer cannot modify those native classes after they exist, so their inheritance relationships are determined by the installed Shopware patch release. The Product Stream compatibility bridge is proposed in [shopware/shopware#20607](https://github.com/shopware/shopware/pull/20607); until that change is present, the native abstract class does not implement its predecessor interface.

## Supported versions

The current draft targets Shopware 6.6, 6.7, and 6.8. Every polyfill is conditional and becomes a no-op when Shopware provides the native API.

## Testing

```bash
composer test
```
