<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\DependencyInjection;

use Keulinho\ShopwarePolyfill\ClassAliasLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MovedServiceAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (ClassAliasLoader::ALIASES as $legacyId => $config) {
            if (
                $config['servicePublic'] === null
                || $container->has($config['canonical'])
                || !$container->has($legacyId)
            ) {
                continue;
            }

            $container
                ->setAlias($config['canonical'], $legacyId)
                ->setPublic($config['servicePublic']);
        }
    }
}
