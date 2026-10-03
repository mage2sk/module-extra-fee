<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteFee extends AbstractDb
{
    const TABLE_NAME = 'panth_extra_fee_quote';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}
