<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel\FeeRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\ExtraFee\Model\FeeRule as FeeRuleModel;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'rule_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(FeeRuleModel::class, FeeRuleResource::class);
    }

    /**
     * Add active filter with date range validation
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $now = (new \DateTime())->format('Y-m-d');

        $this->addFieldToFilter('is_active', 1);
        $this->addFieldToFilter(
            'date_from',
            [
                ['null' => true],
                ['lteq' => $now]
            ]
        );
        $this->addFieldToFilter(
            'date_to',
            [
                ['null' => true],
                ['gteq' => $now]
            ]
        );

        return $this;
    }
}
