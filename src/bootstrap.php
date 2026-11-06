<?php declare(strict_types=1);

use Keulinho\ShopwarePolyfill\ClassAliasLoader;

ClassAliasLoader::registerAll();

foreach (['McpToolDependsOn', 'McpToolGroup', 'McpToolRequires'] as $name) {
    if (!class_exists('Shopware\\Core\\Framework\\Mcp\\Attribute\\' . $name)) {
        require __DIR__ . '/Polyfill/Mcp/' . $name . '.php';
    }
}
unset($name);

if (!interface_exists('Shopware\\Core\\Framework\\Mcp\\Context\\McpContextProviderInterface')) {
    require __DIR__ . '/Polyfill/Mcp/McpContextProviderInterface.php';
}

if (!class_exists('Shopware\\Core\\Framework\\Mcp\\Tool\\McpToolResponse')) {
    require __DIR__ . '/Polyfill/Mcp/McpToolResponse.php';
}

if (!class_exists('Shopware\\Core\\Framework\\Event\\SensitiveMail')) {
    require __DIR__ . '/Polyfill/SensitiveMail.php';
}

if (!class_exists('Shopware\\Core\\Framework\\Webhook\\NotHookable')) {
    require __DIR__ . '/Polyfill/NotHookable.php';
}

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

if (!interface_exists('Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\DynamicallyScheduledTaskHandler')) {
    require __DIR__ . '/Polyfill/DynamicallyScheduledTaskHandler.php';
}
