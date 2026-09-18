<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests\DependencyInjection;

use Keulinho\ShopwarePolyfill\DependencyInjection\MovedServiceAliasPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MovedServiceAliasPassTest extends TestCase
{
    public function testRegistersAvailableLegacyServicesUnderCanonicalIds(): void
    {
        $container = new ContainerBuilder();
        $container->register('Shopware\\Administration\\Controller\\NotificationController')->setPublic(true);
        $container->register('Shopware\\Administration\\Notification\\NotificationDefinition');
        $container->register('Shopware\\Elasticsearch\\Product\\SearchConfigLoader');

        (new MovedServiceAliasPass())->process($container);

        $controllerAlias = $container->getAlias('Shopware\\Core\\Framework\\Notification\\Api\\NotificationController');
        static::assertSame('Shopware\\Administration\\Controller\\NotificationController', (string) $controllerAlias);
        static::assertTrue($controllerAlias->isPublic());

        $definitionAlias = $container->getAlias('Shopware\\Core\\Framework\\Notification\\NotificationDefinition');
        static::assertSame('Shopware\\Administration\\Notification\\NotificationDefinition', (string) $definitionAlias);
        static::assertFalse($definitionAlias->isPublic());

        $searchConfigAlias = $container->getAlias('Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\SearchConfigLoader');
        static::assertSame('Shopware\\Elasticsearch\\Product\\SearchConfigLoader', (string) $searchConfigAlias);
        static::assertFalse($searchConfigAlias->isPublic());
    }

    public function testKeepsExistingCanonicalService(): void
    {
        $container = new ContainerBuilder();
        $container->register('Shopware\\Administration\\Notification\\NotificationDefinition');
        $canonical = $container->register('Shopware\\Core\\Framework\\Notification\\NotificationDefinition');

        (new MovedServiceAliasPass())->process($container);

        static::assertFalse($container->hasAlias('Shopware\\Core\\Framework\\Notification\\NotificationDefinition'));
        static::assertSame($canonical, $container->getDefinition('Shopware\\Core\\Framework\\Notification\\NotificationDefinition'));
    }
}
