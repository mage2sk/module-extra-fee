<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class PaymentMethods implements OptionSourceInterface
{
    /**
     * @var PaymentHelper
     */
    private PaymentHelper $paymentHelper;

    /**
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentHelper $paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        $payments = $this->paymentHelper->getPaymentMethodList(true, true, true);

        foreach ($payments as $code => $title) {
            $options[] = [
                'value' => $code,
                'label' => $title,
            ];
        }

        return $options;
    }
}
