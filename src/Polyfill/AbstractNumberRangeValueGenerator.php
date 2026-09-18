<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;

abstract class AbstractNumberRangeValueGenerator implements NumberRangeValueGeneratorInterface
{
    abstract public function getValue(string $type, Context $context, ?string $salesChannelId, bool $preview = false): string;

    /**
     * @deprecated tag:v6.8.0 - use previewPatternByNumberRangeId() instead
     */
    abstract public function previewPattern(string $definition, ?string $pattern, int $start): string;

    abstract public function previewPatternByNumberRangeId(string $numberRangeId, ?string $pattern = null, ?int $start = null): string;

    abstract protected function getDecorated(): self;
}
