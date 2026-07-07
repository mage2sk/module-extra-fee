<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel\QuoteFee;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\ExtraFee\Model\QuoteFee as QuoteFeeModel;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(QuoteFeeModel::class, QuoteFeeResource::class);
    }

    public function addQuoteFilter(int $quoteId): self
    {
        $this->addFieldToFilter('quote_id', $quoteId);
        return $this;
    }
}
