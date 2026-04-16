<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeeType implements OptionSourceInterface
{
    public const FIXED = 'fixed';
    public const PERCENT = 'percent';
    public const COMBINED = 'combined';
    public const FIXED_MINIMUM = 'fixed_minimum';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIXED, 'label' => __('Fixed Amount')],
            ['value' => self::PERCENT, 'label' => __('Percentage of Subtotal')],
            ['value' => self::COMBINED, 'label' => __('Fixed + Percentage')],
            ['value' => self::FIXED_MINIMUM, 'label' => __('Percentage with Fixed Minimum')],
        ];
    }
}
