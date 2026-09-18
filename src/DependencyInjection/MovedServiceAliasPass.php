<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\DependencyInjection;

use Keulinho\ShopwarePolyfill\ClassAliasLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MovedServiceAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (ClassAliasLoader::ALIASES as $legacyId => $canonicalId) {
            if ($container->has($canonicalId) || !$container->has($legacyId)) {
                continue;
            }

            $container
                ->setAlias($canonicalId, $legacyId)
                ->setPublic($container->findDefinition($legacyId)->isPublic());
        }
    }
}
