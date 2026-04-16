<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TaxDisplay implements OptionSourceInterface
{
    public const EXCLUDING_TAX = 1;
    public const INCLUDING_TAX = 2;
    public const BOTH = 3;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::EXCLUDING_TAX, 'label' => __('Excluding Tax')],
            ['value' => self::INCLUDING_TAX, 'label' => __('Including Tax')],
            ['value' => self::BOTH, 'label' => __('Including & Excluding Tax')],
        ];
    }
}
