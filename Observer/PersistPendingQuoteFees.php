<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Panth\ExtraFee\Model\Total\Quote\ExtraFee as QuoteTotal;

class PersistPendingQuoteFees implements ObserverInterface
{
    public function __construct(
        private readonly QuoteTotal $quoteTotal
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote || $quote->getData(QuoteTotal::PENDING_FEES_KEY) === null) {
            return;
        }

        $this->quoteTotal->persistPendingFees($quote);
    }
}
