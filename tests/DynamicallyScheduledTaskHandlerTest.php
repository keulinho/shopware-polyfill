<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

final class DynamicallyScheduledTaskHandlerTest extends TestCase
{
    public function testInterfaceIsAvailableWithTheNativeContract(): void
    {
        $method = new \ReflectionMethod(DynamicallyScheduledTaskHandler::class, 'getNextExecutionTime');
        $parameters = $method->getParameters();

        $taskType = $parameters[0]->getType();
        static::assertInstanceOf(ReflectionNamedType::class, $taskType);
        static::assertSame(ScheduledTask::class, $taskType->getName());

        $taskEntityType = $parameters[1]->getType();
        static::assertInstanceOf(ReflectionNamedType::class, $taskEntityType);
        static::assertSame(ScheduledTaskEntity::class, $taskEntityType->getName());

        $returnType = $method->getReturnType();
        static::assertInstanceOf(ReflectionNamedType::class, $returnType);
        static::assertSame(\DateTimeInterface::class, $returnType->getName());
        static::assertTrue($returnType->allowsNull());
    }
}
