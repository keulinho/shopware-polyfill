<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill;

use Keulinho\ShopwarePolyfill\DependencyInjection\MovedServiceAliasPass;
use Keulinho\ShopwarePolyfill\Routing\RoutePolyfillLoader;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class ShopwarePolyfillBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MovedServiceAliasPass());
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        parent::configureRoutes($routes, $environment);

        (new RoutePolyfillLoader())->addPathAliases($routes);
    }

    public function configureRouteOverwrites(RoutingConfigurator $routes, string $environment): void
    {
        parent::configureRouteOverwrites($routes, $environment);

        (new RoutePolyfillLoader())->addMethodOverwrites($routes);
    }
}
