<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\Data\OptionSourceInterface;

class Countries implements OptionSourceInterface
{
    /**
     * @var Country
     */
    private Country $countrySource;

    /**
     * @param Country $countrySource
     */
    public function __construct(Country $countrySource)
    {
        $this->countrySource = $countrySource;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return $this->countrySource->toOptionArray();
    }
}
