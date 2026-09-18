<?php declare(strict_types=1);

use Keulinho\ShopwarePolyfill\ClassAliasLoader;

ClassAliasLoader::register(
    'Shopware\\Administration\\Controller\\NotificationController',
    'Shopware\\Core\\Framework\\Notification\\Api\\NotificationController',
);
ClassAliasLoader::register(
    'Shopware\\Administration\\Notification\\NotificationCollection',
    'Shopware\\Core\\Framework\\Notification\\NotificationCollection',
);
ClassAliasLoader::register(
    'Shopware\\Administration\\Notification\\NotificationDefinition',
    'Shopware\\Core\\Framework\\Notification\\NotificationDefinition',
);
ClassAliasLoader::register(
    'Shopware\\Administration\\Notification\\NotificationEntity',
    'Shopware\\Core\\Framework\\Notification\\NotificationEntity',
);
ClassAliasLoader::register(
    'Shopware\\Elasticsearch\\Product\\SearchConfigLoader',
    'Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\SearchConfigLoader',
);

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
