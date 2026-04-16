<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;

/**
 * Cart page extra fee totals block.
 */
class ExtraFeeTotals extends Template
{
    /**
     * @var ExtraFeeHelper
     */
    private ExtraFeeHelper $helper;

    /**
     * @var QuoteFeeCollectionFactory
     */
    private QuoteFeeCollectionFactory $quoteFeeCollectionFactory;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var array|null
     */
    private ?array $fees = null;

    /**
     * @param Context $context
     * @param ExtraFeeHelper $helper
     * @param QuoteFeeCollectionFactory $quoteFeeCollectionFactory
     * @param CheckoutSession $checkoutSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        ExtraFeeHelper $helper,
        QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->quoteFeeCollectionFactory = $quoteFeeCollectionFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get extra fees for the current quote.
     *
     * @return array
     */
    public function getExtraFees(): array
    {
        if ($this->fees !== null) {
            return $this->fees;
        }

        $this->fees = [];
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            return $this->fees;
        }

        $collection = $this->quoteFeeCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', (int) $quote->getId());

        foreach ($collection as $fee) {
            $feeAmount = (float) $fee->getData('fee_amount');
            $taxAmount = (float) $fee->getData('tax_amount');

            if (!$this->helper->isShowZeroFees() && $feeAmount <= 0.0001) {
                continue;
            }

            $this->fees[] = [
                'label'            => (string) $fee->getData('fee_label'),
                'fee_type'         => (string) $fee->getData('fee_type'),
                'base_fee_amount'  => (float) $fee->getData('base_fee_amount'),
                'fee_amount'       => $feeAmount,
                'base_tax_amount'  => (float) $fee->getData('base_tax_amount'),
                'tax_amount'       => $taxAmount,
                'fee_amount_incl_tax' => $feeAmount + $taxAmount,
            ];
        }

        return $this->fees;
    }

    /**
     * Check if the block should be displayed.
     *
     * @return bool
     */
    public function canShow(): bool
    {
        return $this->helper->isEnabled() && $this->helper->isShowInCart();
    }

    /**
     * Get the configured fee display title.
     *
     * @return string
     */
    public function getFeeDisplayTitle(): string
    {
        return $this->helper->getFeeDisplayTitle();
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
     * Check if fee breakdown is enabled.
     *
     * @return bool
     */
    public function isShowFeeBreakdown(): bool
    {
        return $this->helper->isShowFeeBreakdown();
    }

    /**
     * Check if small order fee warning should be shown.
     *
     * @return bool
     */
    public function isSmallOrderFeeActive(): bool
    {
        if (!$this->helper->isSmallOrderFeeEnabled()) {
            return false;
        }

        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return false;
        }

        $subtotal = (float) $quote->getSubtotal();
        return $subtotal < $this->helper->getSmallOrderMinAmount();
    }

    /**
     * Get small order fee warning message with replacements.
     *
     * @return string
     */
    public function getSmallOrderMessage(): string
    {
        $message = $this->helper->getSmallOrderMessage();
        $quote = $this->checkoutSession->getQuote();
        $currencyCode = $quote ? $quote->getQuoteCurrencyCode() : '';

        $minAmount = $this->formatPrice($this->helper->getSmallOrderMinAmount(), $currencyCode);
        $feeAmount = $this->formatPrice($this->helper->getSmallOrderFeeAmount(), $currencyCode);

        return str_replace(['%1', '%2'], [$minAmount, $feeAmount], $message);
    }

    /**
     * Format a price value with currency symbol.
     *
     * @param float $amount
     * @param string $currencyCode
     * @return string
     */
    public function formatPrice(float $amount, string $currencyCode = ''): string
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getStore()) {
            return $quote->getStore()->getCurrentCurrency()->format($amount, [], false);
        }

        return number_format($amount, 2);
    }

    /**
     * Get the helper instance for use in templates.
     *
     * @return ExtraFeeHelper
     */
    public function getHelper(): ExtraFeeHelper
    {
        return $this->helper;
    }

    /**
     * Get the Escaper instance for template use.
     *
     * @return \Magento\Framework\Escaper
     */
    public function getEscaper(): \Magento\Framework\Escaper
    {
        return $this->_escaper;
    }
}
