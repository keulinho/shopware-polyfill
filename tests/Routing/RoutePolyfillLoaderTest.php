<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests\Routing;

use Keulinho\ShopwarePolyfill\Routing\RoutePolyfillLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

final class RoutePolyfillLoaderTest extends TestCase
{
    public function testAddsMethodToExistingRouteContract(): void
    {
        $collection = new RouteCollection();
        $loader = new RoutePolyfillLoader(methodAdditions: [[
            'class' => LegacyMethodController::class,
            'method' => 'load',
            'route' => 'fixture.method',
            'addMethod' => 'GET',
        ]]);

        $loader->addMethodOverwrites($this->configurator($collection));

        $route = $collection->get('fixture.method');
        static::assertNotNull($route);
        static::assertSame('/fixture', $route->getPath());
        static::assertSame(['POST', 'GET'], $route->getMethods());
        static::assertSame(['store-api'], $route->getDefault('_routeScope'));
        static::assertSame('fixture', $route->getDefault('_entity'));
        static::assertSame(LegacyMethodController::class . '::load', $route->getDefault('_controller'));
    }

    public function testKeepsNativeMethodRoute(): void
    {
        $collection = new RouteCollection();
        $loader = new RoutePolyfillLoader(methodAdditions: [[
            'class' => NativeMethodController::class,
            'method' => 'load',
            'route' => 'fixture.native-method',
            'addMethod' => 'GET',
        ]]);

        $loader->addMethodOverwrites($this->configurator($collection));

        static::assertCount(0, $collection);
    }

    public function testAddsNewPathForLegacyController(): void
    {
        $collection = new RouteCollection();
        $loader = new RoutePolyfillLoader(pathAliases: [[
            'class' => LegacyPathController::class,
            'method' => 'load',
            'route' => 'fixture.path',
            'alias' => 'fixture.path.polyfill',
            'path' => '/new-path',
        ]]);

        $loader->addPathAliases($this->configurator($collection));

        $route = $collection->get('fixture.path.polyfill');
        static::assertNotNull($route);
        static::assertSame('/new-path', $route->getPath());
        static::assertSame(['GET'], $route->getMethods());
        static::assertSame(['storefront'], $route->getDefault('_routeScope'));
        static::assertSame(LegacyPathController::class . '::load', $route->getDefault('_controller'));
    }

    public function testKeepsNativePathRoute(): void
    {
        $collection = new RouteCollection();
        $loader = new RoutePolyfillLoader(pathAliases: [[
            'class' => NativePathController::class,
            'method' => 'load',
            'route' => 'fixture.native-path',
            'alias' => 'fixture.native-path.polyfill',
            'path' => '/new-path',
        ]]);

        $loader->addPathAliases($this->configurator($collection));

        static::assertCount(0, $collection);
    }

    private function configurator(RouteCollection $collection): RoutingConfigurator
    {
        return new RoutingConfigurator(
            $collection,
            new PhpFileLoader(new FileLocator()),
            __FILE__,
            __FILE__,
        );
    }
}

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class LegacyMethodController
{
    #[Route(path: '/fixture', name: 'fixture.method', defaults: ['_entity' => 'fixture'], methods: ['POST'])]
    public function load(): void
    {
    }
}

final class NativeMethodController
{
    #[Route(path: '/fixture', name: 'fixture.native-method', methods: ['POST', 'GET'])]
    public function load(): void
    {
    }
}

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class LegacyPathController
{
    #[Route(path: '/old-path', name: 'fixture.path', methods: ['GET'])]
    public function load(): void
    {
    }
}

final class NativePathController
{
    #[Route(path: '/new-path', name: 'fixture.native-path', methods: ['GET'])]
    public function load(): void
    {
    }
}
