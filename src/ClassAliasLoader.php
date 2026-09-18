<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill;

final class ClassAliasLoader
{
    /** @var array<string, string> */
    public const ALIASES = [
        'Shopware\\Administration\\Controller\\NotificationController' => 'Shopware\\Core\\Framework\\Notification\\Api\\NotificationController',
        'Shopware\\Administration\\Notification\\NotificationCollection' => 'Shopware\\Core\\Framework\\Notification\\NotificationCollection',
        'Shopware\\Administration\\Notification\\NotificationDefinition' => 'Shopware\\Core\\Framework\\Notification\\NotificationDefinition',
        'Shopware\\Administration\\Notification\\NotificationEntity' => 'Shopware\\Core\\Framework\\Notification\\NotificationEntity',
        'Shopware\\Elasticsearch\\Product\\SearchConfigLoader' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\SearchConfigLoader',
    ];

    public static function registerAll(): void
    {
        foreach (self::ALIASES as $legacyClass => $canonicalClass) {
            self::register($legacyClass, $canonicalClass);
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
