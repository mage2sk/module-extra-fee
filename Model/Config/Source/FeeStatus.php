<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeeStatus implements OptionSourceInterface
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ACTIVE, 'label' => __('Active')],
            ['value' => self::INACTIVE, 'label' => __('Inactive')],
        ];
    }
}
