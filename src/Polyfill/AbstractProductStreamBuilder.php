<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

abstract class AbstractProductStreamBuilder implements ProductStreamBuilderInterface
{
    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;

    /**
     * @deprecated tag:v6.8.0 - use enrichCriteria() instead
     *
     * @return list<Filter>
     */
    public function buildFilters(string $id, Context $context): array
    {
        $criteria = new Criteria();
        $this->enrichCriteria($criteria, $id, $context);

        return array_values($criteria->getFilters());
    }
}
