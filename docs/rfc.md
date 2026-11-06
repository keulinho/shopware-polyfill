I would like feedback on a small Composer package that lets extensions adopt selected newer Shopware PHP contracts while still supporting older installations.

The experimental implementation is [keulinho/shopware-polyfill](https://github.com/keulinho/shopware-polyfill).
It is a proof of concept in my personal namespace, with no stable compatibility promise yet.

## Problem

An extension can usually branch around a method call, but adopting a new parent class or interface is harder: PHP must resolve that symbol when loading the class.
Maintaining conditional class declarations or separate implementations makes testing and static analysis harder.

The package conditionally supplies selected missing symbols and integration aliases.
The installed Shopware implementation always wins.
This follows the Composer polyfill pattern, but eligibility depends on the individual Shopware contract.
Providing a symbol does not make older core consume it or backport the surrounding feature.

## Proposed scope

The primary scope is PHP contracts and metadata that can be supplied without inventing application behavior:

- Identity-preserving class moves, with matching service aliases where needed.
- Replacement abstract classes that satisfy the old provider contract.
- New interfaces whose method types already exist on the older version.
- Metadata attributes whose constructors and reflection contracts can be preserved independently of runtime behavior.

Each candidate needs an explicit compatibility boundary and tests against absent, partially native, and native definitions.
The draft currently targets 6.6 and 6.7, and declares forward compatibility with 6.8; that declaration is not evidence of a completed full-version test matrix.

### Moved classes and services

The demo provides canonical names for the notification classes and `SearchConfigLoader` moved in [#20525](https://github.com/shopware/shopware/pull/20525).
On older versions it aliases the existing legacy class, preserving object identity.
The bundle registers matching aliases for the affected services only when their canonical IDs are missing.

### Provider contracts

The demo supplies `AbstractProductStreamBuilder` and `AbstractNumberRangeValueGenerator` when their native definitions are absent and their predecessor interfaces exist.
The polyfilled abstract classes implement the predecessor interfaces.
A plugin can extend one replacement type instead of conditionally declaring two classes.

For this to work throughout the transition, the native replacement must also retain the old interface until removal.
The Product Stream bridge [#20607](https://github.com/shopware/shopware/pull/20607) is merged and present in 6.7.16.0.
The native classes in 6.7.12 through 6.7.15 lack that bridge; Composer cannot change their inheritance.
The Number Range abstract class still lacks an equivalent bridge in 6.7.16.0.
These gaps prevent treating the provider examples as universally compatible across the declared version range.

The Product Stream bridge carries filters back to the legacy API; additional criteria state is not representable there.
Number Range providers must implement both preview methods because a number-range type cannot be translated universally into a concrete number-range ID.

An existing older core service also does not become an instance of the new abstract class.
Consumers may need an explicitly wired union type or a case-specific adapter:

```php
public function __construct(
    private readonly AbstractProductStreamBuilder|ProductStreamBuilderInterface $builder,
) {
}
```

### New interfaces

The demo supplies `DynamicallyScheduledTaskHandler`, introduced in 6.7.13.
A handler can implement its `getNextExecutionTime()` method while retaining its existing `rescheduleTask()` override.
Older core invokes the legacy override; newer core recognizes the interface and uses the replacement method.
The package supplies the missing declaration, while the plugin retains responsibility for compatible implementations.

### Extension marker attributes

Attribute eligibility starts with intended extension use.
Core's BC-change attributes are internal planning metadata and are excluded.

Reviewing attribute declarations added after 6.7.0.0 identifies these extension-facing markers:

| Marker | Availability | Use and compatibility boundary |
|---|---|---|
| [`NotHookable`](https://github.com/shopware/shopware/blob/v6.7.14.1/src/Core/Framework/Webhook/NotHookable.php) | 6.7.14.1 | Marks a flow event as excluded from webhooks; older core needs the corresponding policy to enforce it. |
| [`SensitiveMail`](https://github.com/shopware/shopware/blob/trunk/src/Core/Framework/Event/SensitiveMail.php) | Merged on trunk, not in published 6.7 tags through 6.7.16.0 | Lets extension `MailAware` events opt into metadata-only mail logging; older core needs the flow and mail-log changes to honor it. |

`IsFlowEventAware` is also an extension-facing marker, but already exists in 6.7.0.0.
The proposed demo addition supplies both marker declarations, preserving their class targets and empty constructor contracts.
Native definitions always take precedence.

```php
use Shopware\Core\Framework\Webhook\NotHookable;
use Shopware\Core\Framework\Event\FlowEventAware;

#[NotHookable]
final class PluginFlowEvent implements FlowEventAware
{
    // Existing event implementation.
}
```

This allows one event class to carry newer metadata across versions.
PHP already permits unresolved attribute names on declarations; supplying the class additionally supports lookup and reflection instantiation.
It does not make an older core inspect the marker.
In particular, a declaration polyfill must never be presented as webhook suppression or mail-log redaction.
Extensions relying on those protections must require the corresponding core implementation or supply their own tested enforcement on older versions.
`SensitiveMail` is copied from [the merged native implementation](https://github.com/shopware/shopware/blob/e41e3f38b5d93b56534a8a8196bff32abd2250d8/src/Core/Framework/Event/SensitiveMail.php).
It is explicitly experimental until its upstream contract is released.

Other extension attributes added during 6.7 have stronger runtime dependencies:

- DAL `ReverseInherited` arrived in 6.7.7.0; `SearchRanking`, `Password`, and `ListField` arrived in 6.7.9.0.
  Their compiler, flag, and serializer support must also exist for their semantics to work.
- MCP `McpToolRequires` and `McpToolDependsOn` arrived in 6.7.11.0, and `McpToolGroup` in 6.7.14.0.
  These are designed for tools, including extension tools, and are reasonable declaration-polyfill candidates.
  They do not backport MCP discovery, dependency handling, or grouping.
  `McpToolRequires` is informational and does not enforce privileges.
- Existing attribute constructors gained options such as DAL entity inheritance awareness or field maximum length.
  A polyfill cannot extend an already defined native constructor.

The demo leaves these broader runtime integrations out.

### Inactive MCP extensions on older core

An extension should be able to ship its MCP integration in one implementation even on core versions that do not expose MCP.
Conditional MCP attributes and compatible interfaces can remove declaration barriers for that use case.
Without an MCP consumer, an ordinary service's `shopware.mcp.tool` tag does not invoke it; an unused private tool can be removed from the compiled container.

This is a useful direction, but attributes and interfaces alone do not guarantee unconditional registration:

- PHP still needs every parent class and implemented interface when loading the tool.
- A polyfilled `McpContextProviderInterface` does not provide the native service an autowired constructor may require.
- `McpToolResponse`, the usual tool parent, has executable response and error handling; it is not an empty metadata contract.
- The SDK's `McpTool` attribute and related value types should come from the real `mcp/sdk` dependency rather than copied SDK polyfills.
- Versions with partial native MCP support can already consume some metadata and ignore other metadata, so their integration behavior needs separate validation.

The smallest useful follow-up would cover the three Shopware MCP attributes and selected compatible interfaces, then prove one representative extension compiles on old core and is discovered and callable on native MCP core.
Any required base-class compatibility or service-registration guard must be part of that proof.
The current demo does not yet include this MCP layer.

## Installation and activation

```bash
composer require keulinho/shopware-polyfill:dev-main
```

Composer loads the PHP symbols and metadata automatically.
Only plugins needing service aliases or the route experiment register `ShopwarePolyfillBundle` through `getAdditionalBundles()`.
Shopware deduplicates additional bundles by bundle name.

The existing bundle also registers a separate HTTP route experiment, documented in the repository README.
It adds selected methods and path aliases using existing controllers and route metadata, without backporting newer caching infrastructure.
Registering the bundle activates both service aliases and these route additions.
The discussion feedback correctly identifies a different audience here: external API clients cannot assume a merchant installed a plugin carrying this bundle.
Before a stable release, we should decide whether route compatibility belongs in a separate opt-in package.

## Tooling and validation

The [phpstan-shopware proof of concept](https://github.com/shopware/phpstan-shopware/pull/50) explores opt-in migration guidance when the polyfill is installed.
A supported integration should discover verified migration relationships from one registry instead of duplicating lists in runtime code and analysis rules.
Package presence alone does not prove that a native transition gap or consumer incompatibility has been solved.

Tests must cover the old execution path as well as symbol loading and native precedence.
For metadata, reflection and constructor compatibility are the relevant behavior.
For provider bridges, DI, callbacks, and routes, tests must also prove that the intended integration actually runs.
Full supported-version testing remains a requirement before promising compatibility.

## Boundaries and next steps

The package should not guess missing identifiers, backport security-sensitive behavior, make old core dispatch new events, alter loaded classes, or hide meaningful version differences.
Ordinary method migrations normally stay in the plugin as parallel old and new methods; they do not need generic compatibility traits.

Administration and Storefront compatibility remain separate open areas.
The feedback about Meteor components, slots, and Twig compilation is valuable, but this PHP demo does not solve those problems.
Concrete examples should drive separate proposals with their own integration tests.

I would especially appreciate feedback on:

1. Which extension-facing markers are useful as declaration polyfills even when older core does not consume them?
2. Which missing PHP symbols actually force conditional class declarations in maintained extensions?
3. Can core consistently preserve predecessor interfaces throughout replacement transitions?
4. Should HTTP route compatibility be separated from the PHP package?
5. Which concrete Administration or Twig migrations would benefit most from a similar approach?
