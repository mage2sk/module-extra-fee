<?php

declare(strict_types=1);

namespace Panth\ExtraFee\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;

/**
 * ViewModel for rendering extra fee data in templates.
 */
class ExtraFee implements ArgumentInterface
{
    /**
     * @param Helper $helper
     * @param OrderFeeCollectionFactory $orderFeeCollectionFactory
     * @param QuoteFeeCollectionFactory $quoteFeeCollectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly Helper $helper,
        private readonly OrderFeeCollectionFactory $orderFeeCollectionFactory,
        private readonly QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * Check if module is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    /**
     * Get order fees by order ID.
     *
     * @param int $orderId
     * @return array
     */
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

    /**
     * Get quote fees by quote ID.
     *
     * @param int $quoteId
     * @return array
     */
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

    /**
     * Format price amount.
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, true, PriceCurrencyInterface::DEFAULT_PRECISION);
    }

    /**
     * Get tax display type (1 = Excl, 2 = Incl, 3 = Both).
     *
     * @return int
     */
    public function getTaxDisplay(): int
    {
        return $this->helper->getTaxDisplay();
    }

    /**
     * Check if fee breakdown should be shown.
     *
     * @return bool
     */
    public function isShowFeeBreakdown(): bool
    {
        return $this->helper->isShowFeeBreakdown();
    }

    /**
     * Get the fee display title.
     *
     * @return string
     */
    public function getFeeDisplayTitle(): string
    {
        return $this->helper->getFeeDisplayTitle();
    }
}
