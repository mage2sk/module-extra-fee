<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\Data\OptionSourceInterface;

class Countries implements OptionSourceInterface
{
    public function __construct(
        private readonly Country $countrySource
    ) {
    }

    public function toOptionArray(): array
    {
        $options = $this->countrySource->toOptionArray(true);
        // Remove the empty "Please select" option - not needed for multiselect
        return array_filter($options, fn($opt) => !empty($opt['value']));
    }
}
