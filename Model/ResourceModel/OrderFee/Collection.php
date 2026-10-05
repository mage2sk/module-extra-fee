<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel\OrderFee;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\ExtraFee\Model\OrderFee as OrderFeeModel;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(OrderFeeModel::class, OrderFeeResource::class);
    }

    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter('order_id', $orderId);
        return $this;
    }
}
