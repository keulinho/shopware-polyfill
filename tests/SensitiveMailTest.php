<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\SensitiveMail;

final class SensitiveMailTest extends TestCase
{
    public function testMarkerCanBeReadAndInstantiatedThroughReflection(): void
    {
        $attributes = (new \ReflectionClass(SensitiveEventFixture::class))->getAttributes(SensitiveMail::class);

        static::assertCount(1, $attributes);
        static::assertInstanceOf(SensitiveMail::class, $attributes[0]->newInstance());
        static::assertSame([], $attributes[0]->getArguments());

        $marker = new \ReflectionClass(SensitiveMail::class);
        static::assertNull($marker->getConstructor());
        static::assertSame(\Attribute::TARGET_CLASS, $marker->getAttributes(\Attribute::class)[0]->newInstance()->flags);
    }

    public function testBootstrapPreservesAnExistingDefinition(): void
    {
        $file = (new \ReflectionClass(SensitiveMail::class))->getFileName();

        require __DIR__ . '/../src/bootstrap.php';

        static::assertSame($file, (new \ReflectionClass(SensitiveMail::class))->getFileName());
    }
}

#[SensitiveMail]
final class SensitiveEventFixture
{
}
