<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill;

final class ClassAliasLoader
{
    public static function register(string $legacyClass, string $canonicalClass): bool
    {
        if (class_exists($canonicalClass) || !class_exists($legacyClass)) {
            return false;
        }

        return class_alias($legacyClass, $canonicalClass);
    }
}
