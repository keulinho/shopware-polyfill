<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill;

use Keulinho\ShopwarePolyfill\DependencyInjection\MovedServiceAliasPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ShopwarePolyfillBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MovedServiceAliasPass());
    }
}
