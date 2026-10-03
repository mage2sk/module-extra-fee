<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\ExtraFee\Model\Total\Quote\ExtraFee as QuoteTotal;

class MarkMultishippingOrder implements ObserverInterface
{
    public const SKIP_FLAG = 'panth_extra_fee_skip';

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $address = $observer->getEvent()->getAddress();
        if (!$order || !$address) {
            return;
        }

        if ($address->getData(QuoteTotal::CARRIER_FLAG) !== true) {
            $order->setData(self::SKIP_FLAG, true);
        }
    }
}
