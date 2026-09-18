<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill;

final class ClassAliasLoader
{
    /**
     * A null servicePublic value means that the class has no service alias.
     *
     * @var array<string, array{canonical: string, servicePublic: bool|null}>
     */
    public const ALIASES = [
        'Shopware\\Administration\\Controller\\NotificationController' => [
            'canonical' => 'Shopware\\Core\\Framework\\Notification\\Api\\NotificationController',
            'servicePublic' => true,
        ],
        'Shopware\\Administration\\Notification\\NotificationCollection' => [
            'canonical' => 'Shopware\\Core\\Framework\\Notification\\NotificationCollection',
            'servicePublic' => null,
        ],
        'Shopware\\Administration\\Notification\\NotificationDefinition' => [
            'canonical' => 'Shopware\\Core\\Framework\\Notification\\NotificationDefinition',
            'servicePublic' => false,
        ],
        'Shopware\\Administration\\Notification\\NotificationEntity' => [
            'canonical' => 'Shopware\\Core\\Framework\\Notification\\NotificationEntity',
            'servicePublic' => null,
        ],
        'Shopware\\Elasticsearch\\Product\\SearchConfigLoader' => [
            'canonical' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\SearchConfigLoader',
            'servicePublic' => false,
        ],
    ];

    public static function registerAll(): void
    {
        foreach (self::ALIASES as $legacyClass => $config) {
            self::register($legacyClass, $config['canonical']);
        }
    }

    public static function register(string $legacyClass, string $canonicalClass): bool
    {
        if (class_exists($canonicalClass) || !class_exists($legacyClass)) {
            return false;
        }

        return class_alias($legacyClass, $canonicalClass);
    }
}
