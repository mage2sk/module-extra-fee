<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FeeRule extends AbstractDb
{
    /**
     * @var string
     */
    const TABLE_NAME = 'panth_extra_fee_rule';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'rule_id');
    }
}
