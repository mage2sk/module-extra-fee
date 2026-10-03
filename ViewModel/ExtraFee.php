<?php
declare(strict_types=1);

namespace Panth\ExtraFee\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;

class ExtraFee implements ArgumentInterface
{
    public function __construct(
        private readonly Helper $helper,
        private readonly OrderFeeCollectionFactory $orderFeeCollectionFactory,
        private readonly QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    public function getOrderFees(int $orderId): array
    {
        $collection = $this->orderFeeCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);

        $fees = [];
        foreach ($collection as $fee) {
            $fees[] = $fee->getData();
        }

        return $fees;
    }

    public function getQuoteFees(int $quoteId): array
    {
        $collection = $this->quoteFeeCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $quoteId);

        $fees = [];
        foreach ($collection as $fee) {
            $fees[] = $fee->getData();
        }

        return $fees;
    }

    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, true, PriceCurrencyInterface::DEFAULT_PRECISION);
    }

    public function getTaxDisplay(): int
    {
        return $this->helper->getTaxDisplay();
    }

    public function isShowFeeBreakdown(): bool
    {
        return $this->helper->isShowFeeBreakdown();
    }

    public function getFeeDisplayTitle(): string
    {
        return $this->helper->getFeeDisplayTitle();
    }
}
