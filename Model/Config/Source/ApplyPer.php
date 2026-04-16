<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApplyPer implements OptionSourceInterface
{
    public const ORDER = 'order';
    public const PRODUCT = 'product';
    public const QUANTITY = 'quantity';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ORDER, 'label' => __('Per Order')],
            ['value' => self::PRODUCT, 'label' => __('Per Product (per unique line item)')],
            ['value' => self::QUANTITY, 'label' => __('Per Quantity (per unit)')],
        ];
    }
}
