<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\NotHookable;

final class NotHookableTest extends TestCase
{
    public function testMarkerCanBeReadAndInstantiatedThroughReflection(): void
    {
        $attributes = (new \ReflectionClass(PrivateEventFixture::class))->getAttributes(NotHookable::class);

        static::assertCount(1, $attributes);
        static::assertInstanceOf(NotHookable::class, $attributes[0]->newInstance());
        static::assertSame([], $attributes[0]->getArguments());

        $marker = new \ReflectionClass(NotHookable::class);
        static::assertNull($marker->getConstructor());
        static::assertSame(\Attribute::TARGET_CLASS, $marker->getAttributes(\Attribute::class)[0]->newInstance()->flags);
    }

    public function testBootstrapPreservesAnExistingDefinition(): void
    {
        $file = (new \ReflectionClass(NotHookable::class))->getFileName();

        require __DIR__ . '/../src/bootstrap.php';

        static::assertSame($file, (new \ReflectionClass(NotHookable::class))->getFileName());
    }
}

#[NotHookable]
final class PrivateEventFixture
{
}
