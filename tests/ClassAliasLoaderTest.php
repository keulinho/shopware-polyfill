<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests;

use Keulinho\ShopwarePolyfill\ClassAliasLoader;
use PHPUnit\Framework\TestCase;

final class ClassAliasLoaderTest extends TestCase
{
    public function testRegistersCanonicalAliasForLegacyClass(): void
    {
        $canonicalClass = __NAMESPACE__ . '\\CanonicalFixture';

        static::assertTrue(ClassAliasLoader::register(LegacyFixture::class, $canonicalClass));
        static::assertTrue(class_exists($canonicalClass, false));
        static::assertInstanceOf(LegacyFixture::class, new $canonicalClass());
        static::assertFalse(ClassAliasLoader::register(LegacyFixture::class, $canonicalClass));
    }

    public function testDoesNotRegisterAliasForMissingLegacyClass(): void
    {
        static::assertFalse(ClassAliasLoader::register(
            __NAMESPACE__ . '\\MissingLegacyFixture',
            __NAMESPACE__ . '\\MissingCanonicalFixture',
        ));
    }
}

final class LegacyFixture
{
}
