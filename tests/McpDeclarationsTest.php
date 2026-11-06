<?php declare(strict_types=1);

namespace Keulinho\ShopwarePolyfill\Tests;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProviderInterface;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpDeclarationsTest extends TestCase
{
    public function testAttributesPreserveTheirMetadata(): void
    {
        $tool = new \ReflectionClass(InactivePluginTool::class);

        static::assertSame('plugin', $tool->getAttributes(McpToolGroup::class)[0]->newInstance()->group);
        $dependencies = $tool->getAttributes(McpToolDependsOn::class);
        static::assertCount(2, $dependencies);
        static::assertSame('plugin-first', $dependencies[0]->newInstance()->toolName);
        static::assertSame('plugin-second', $dependencies[1]->newInstance()->toolName);
        $requirements = $tool->getAttributes(McpToolRequires::class);
        static::assertCount(2, $requirements);
        static::assertSame('product:read', $requirements[0]->newInstance()->privilege);
        static::assertSame([
            'privilege' => null,
            'entityParam' => 'entity',
            'operations' => ['read'],
        ], get_object_vars($requirements[1]->newInstance()));
    }

    public function testToolLoadsAndCompilesWithoutAnMcpRuntime(): void
    {
        $container = new ContainerBuilder();
        $container->register(InactivePluginTool::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('shopware.mcp.tool');

        $container->compile();
        $tool = $container->get(InactivePluginTool::class);

        static::assertInstanceOf(McpToolResponse::class, $tool);
        static::assertInstanceOf(InactivePluginTool::class, $tool);
        static::assertNull($tool->contextProvider);
        static::assertFalse(InactivePluginTool::$invoked);
    }

    public function testProviderImplementationCanLoad(): void
    {
        static::assertInstanceOf(McpContextProviderInterface::class, new InactiveContextProvider());
    }

    public function testBootstrapPreservesExistingDefinitions(): void
    {
        $symbols = [
            McpToolDependsOn::class,
            McpToolGroup::class,
            McpToolRequires::class,
            McpContextProviderInterface::class,
            McpToolResponse::class,
        ];
        $files = [];
        foreach ($symbols as $symbol) {
            $files[$symbol] = (new \ReflectionClass($symbol))->getFileName();
        }

        require __DIR__ . '/../src/bootstrap.php';

        foreach ($files as $symbol => $file) {
            static::assertSame($file, (new \ReflectionClass($symbol))->getFileName());
        }
    }
}

#[McpTool(name: 'plugin-inactive', description: 'An inactive plugin tool')]
#[McpToolGroup('plugin')]
#[McpToolDependsOn('plugin-first')]
#[McpToolDependsOn('plugin-second')]
#[McpToolRequires('product:read')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
final class InactivePluginTool extends McpToolResponse
{
    public static bool $invoked = false;

    public function __construct(public readonly ?McpContextProviderInterface $contextProvider = null)
    {
    }

    public function __invoke(): string
    {
        self::$invoked = true;

        return 'invoked';
    }
}

final class InactiveContextProvider implements McpContextProviderInterface
{
    public function getContext(): never
    {
        throw new \LogicException('An inactive context provider must not be invoked.');
    }
}
