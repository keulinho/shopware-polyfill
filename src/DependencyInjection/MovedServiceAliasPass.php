<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MovedServiceAliasPass implements CompilerPassInterface
{
    /**
     * @var array<string, array{legacy: string, public: bool}>
     */
    private const ALIASES = [
        'Shopware\\Core\\Framework\\Notification\\Api\\NotificationController' => [
            'legacy' => 'Shopware\\Administration\\Controller\\NotificationController',
            'public' => true,
        ],
        'Shopware\\Core\\Framework\\Notification\\NotificationDefinition' => [
            'legacy' => 'Shopware\\Administration\\Notification\\NotificationDefinition',
            'public' => false,
        ],
        'Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\SearchConfigLoader' => [
            'legacy' => 'Shopware\\Elasticsearch\\Product\\SearchConfigLoader',
            'public' => false,
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::ALIASES as $canonicalId => $config) {
            if ($container->has($canonicalId) || !$container->has($config['legacy'])) {
                continue;
            }

            $container
                ->setAlias($canonicalId, $config['legacy'])
                ->setPublic($config['public']);
        }
    }
}
