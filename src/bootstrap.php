<?php declare(strict_types=1);

use Keulinho\ShopwarePolyfill\ClassAliasLoader;

ClassAliasLoader::registerAll();

if (
    !class_exists('Shopware\\Core\\Content\\ProductStream\\Service\\AbstractProductStreamBuilder')
    && interface_exists('Shopware\\Core\\Content\\ProductStream\\Service\\ProductStreamBuilderInterface')
) {
    require __DIR__ . '/Polyfill/AbstractProductStreamBuilder.php';
}

if (
    !class_exists('Shopware\\Core\\System\\NumberRange\\ValueGenerator\\AbstractNumberRangeValueGenerator')
    && interface_exists('Shopware\\Core\\System\\NumberRange\\ValueGenerator\\NumberRangeValueGeneratorInterface')
) {
    require __DIR__ . '/Polyfill/AbstractNumberRangeValueGenerator.php';
}
