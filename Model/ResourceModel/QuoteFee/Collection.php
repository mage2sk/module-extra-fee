<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel\QuoteFee;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\ExtraFee\Model\QuoteFee as QuoteFeeModel;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(QuoteFeeModel::class, QuoteFeeResource::class);
    }

    /**
     * Filter collection by quote ID
     *
     * @param int $quoteId
     * @return $this
     */
    public function addQuoteFilter(int $quoteId): self
    {
        $this->addFieldToFilter('quote_id', $quoteId);
        return $this;
    }
}
