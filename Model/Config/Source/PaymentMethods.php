<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Model\Config;

class PaymentMethods implements OptionSourceInterface
{
    public function __construct(
        private readonly Config $paymentConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        $payments = $this->paymentConfig->getActiveMethods();

        foreach ($payments as $code => $method) {
            $title = $this->scopeConfig->getValue("payment/{$code}/title") ?: $code;
            $options[] = [
                'value' => $code,
                'label' => (string)$title,
            ];
        }

        usort($options, fn($a, $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
