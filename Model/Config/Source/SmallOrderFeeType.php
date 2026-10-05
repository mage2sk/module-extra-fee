<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SmallOrderFeeType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => FeeType::FIXED, 'label' => __('Fixed Amount')],
            ['value' => FeeType::PERCENT, 'label' => __('Percentage of Subtotal')],
        ];
    }
}
