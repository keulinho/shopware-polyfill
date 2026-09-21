<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Routing;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class RoutePolyfillLoader
{
    /**
     * @var list<array{class: class-string, method: string, route: string, addMethod: string}>
     */
    private const METHOD_ADDITIONS = [
        [
            'class' => 'Shopware\Administration\Controller\UserConfigController',
            'method' => 'updateConfigMe',
            'route' => 'api.config_me.update',
            'addMethod' => 'PATCH',
        ],
        [
            'class' => 'Shopware\Core\Content\Media\SalesChannel\MediaRoute',
            'method' => 'load',
            'route' => 'store-api.media.detail',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute',
            'method' => 'load',
            'route' => 'store-api.product.cross-selling',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute',
            'method' => 'load',
            'route' => 'store-api.product.detail',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute',
            'method' => 'load',
            'route' => 'store-api.product.find-variant',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute',
            'method' => 'load',
            'route' => 'store-api.product.listing',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute',
            'method' => 'load',
            'route' => 'store-api.product-review.list',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute',
            'method' => 'load',
            'route' => 'store-api.search',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute',
            'method' => 'load',
            'route' => 'store-api.search.suggest',
            'addMethod' => 'GET',
        ],
        [
            'class' => 'Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute',
            'method' => 'load',
            'route' => 'store-api.landing-page.detail',
            'addMethod' => 'GET',
        ],
    ];

    /**
     * @var list<array{class: class-string, method: string, route: string, alias: string, path: string}>
     */
    private const PATH_ALIASES = [
        [
            'class' => 'Shopware\Storefront\Controller\NavigationController',
            'method' => 'header',
            'route' => 'frontend.header',
            'alias' => 'shopware-polyfill.frontend.header',
            'path' => '/_esi/global/header',
        ],
        [
            'class' => 'Shopware\Storefront\Controller\NavigationController',
            'method' => 'footer',
            'route' => 'frontend.footer',
            'alias' => 'shopware-polyfill.frontend.footer',
            'path' => '/_esi/global/footer',
        ],
    ];

    /**
     * @param list<array{class: class-string, method: string, route: string, addMethod: string}> $methodAdditions
     * @param list<array{class: class-string, method: string, route: string, alias: string, path: string}> $pathAliases
     */
    public function __construct(
        private readonly array $methodAdditions = self::METHOD_ADDITIONS,
        private readonly array $pathAliases = self::PATH_ALIASES,
    ) {
    }

    public function addMethodOverwrites(RoutingConfigurator $routes): void
    {
        foreach ($this->methodAdditions as $definition) {
            $route = $this->findRoute($definition['class'], $definition['method'], $definition['route']);
            if ($route === null || \in_array($definition['addMethod'], $route['methods'], true)) {
                continue;
            }

            $this->addRoute(
                $routes,
                $definition['class'],
                $definition['method'],
                $route,
                $route['path'],
                $route['name'],
                [...$route['methods'], $definition['addMethod']],
            );
        }
    }

    public function addPathAliases(RoutingConfigurator $routes): void
    {
        foreach ($this->pathAliases as $definition) {
            $route = $this->findRoute($definition['class'], $definition['method'], $definition['route']);
            if ($route === null || $route['path'] === $definition['path']) {
                continue;
            }

            $this->addRoute(
                $routes,
                $definition['class'],
                $definition['method'],
                $route,
                $definition['path'],
                $definition['alias'],
                $route['methods'],
            );
        }
    }

    /**
     * @param class-string $class
     *
     * @return array{
     *     path: string|array<string, string>|null,
     *     name: ?string,
     *     requirements: array<string, string|\Stringable>,
     *     options: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     host: ?string,
     *     methods: list<string>,
     *     schemes: list<string>,
     *     condition: ?string
     * }|null
     */
    private function findRoute(string $class, string $method, string $routeName): ?array
    {
        if (!method_exists($class, $method)) {
            return null;
        }

        foreach ((new \ReflectionMethod($class, $method))->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $route = $attribute->newInstance();
            $metadata = $this->metadata($route);
            if ($metadata['name'] === $routeName) {
                return $metadata;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @param array{
     *     path: string|array<string, string>|null,
     *     name: ?string,
     *     requirements: array<string, string|\Stringable>,
     *     options: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     host: ?string,
     *     methods: list<string>,
     *     schemes: list<string>,
     *     condition: ?string
     * } $route
     * @param list<string> $methods
     */
    private function addRoute(
        RoutingConfigurator $routes,
        string $class,
        string $method,
        array $route,
        string|array|null $path,
        ?string $name,
        array $methods,
    ): void {
        if ($path === null || $name === null) {
            return;
        }

        $configurator = $routes
            ->add($name, $path)
            ->defaults($route['defaults'] + $this->classDefaults($class))
            ->requirements($route['requirements'])
            ->options($route['options'])
            ->methods($methods)
            ->controller($class . '::' . $method);

        $this->copyOptionalConfiguration($configurator, $route);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function classDefaults(string $class): array
    {
        $defaults = [];

        foreach ((new \ReflectionClass($class))->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $defaults = $this->metadata($attribute->newInstance())['defaults'] + $defaults;
        }

        return $defaults;
    }

    /**
     * @param array{
     *     host: ?string,
     *     schemes: list<string>,
     *     condition: ?string
     * } $route
     */
    private function copyOptionalConfiguration(RouteConfigurator $configurator, array $route): void
    {
        if ($route['host'] !== null && $route['host'] !== '') {
            $configurator->host($route['host']);
        }

        if ($route['schemes'] !== []) {
            $configurator->schemes($route['schemes']);
        }

        if ($route['condition'] !== null && $route['condition'] !== '') {
            $configurator->condition($route['condition']);
        }
    }

    /**
     * Symfony 7.4 exposes these properties directly and deprecates the getters. Reading the
     * attribute metadata through reflection also works with the private properties in 6.4.
     *
     * @return array{
     *     path: string|array<string, string>|null,
     *     name: ?string,
     *     requirements: array<string, string|\Stringable>,
     *     options: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     host: ?string,
     *     methods: list<string>,
     *     schemes: list<string>,
     *     condition: ?string
     * }
     */
    private function metadata(Route $route): array
    {
        $reflection = new \ReflectionClass($route);

        return [
            'path' => $reflection->getProperty('path')->getValue($route),
            'name' => $reflection->getProperty('name')->getValue($route),
            'requirements' => $reflection->getProperty('requirements')->getValue($route),
            'options' => $reflection->getProperty('options')->getValue($route),
            'defaults' => $reflection->getProperty('defaults')->getValue($route),
            'host' => $reflection->getProperty('host')->getValue($route),
            'methods' => $reflection->getProperty('methods')->getValue($route),
            'schemes' => $reflection->getProperty('schemes')->getValue($route),
            'condition' => $reflection->getProperty('condition')->getValue($route),
        ];
    }
}
