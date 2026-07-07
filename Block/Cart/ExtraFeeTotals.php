<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;

class ExtraFeeTotals extends Template
{
    private ExtraFeeHelper $helper;

    private QuoteFeeCollectionFactory $quoteFeeCollectionFactory;

    private CheckoutSession $checkoutSession;

    private ?array $fees = null;

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

    public function canShow(): bool
    {
        return $this->helper->isEnabled() && $this->helper->isShowInCart();
    }

    public function getFeeDisplayTitle(): string
    {
        return $this->helper->getFeeDisplayTitle();
    }

    public function getTaxDisplay(): int
    {
        return $this->helper->getTaxDisplay();
    }

    public function isShowFeeBreakdown(): bool
    {
        return $this->helper->isShowFeeBreakdown();
    }

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

    public function getSmallOrderMessage(): string
    {
        $message = $this->helper->getSmallOrderMessage();
        $quote = $this->checkoutSession->getQuote();
        $currencyCode = $quote ? $quote->getQuoteCurrencyCode() : '';

        $minAmount = $this->formatPrice($this->helper->getSmallOrderMinAmount(), $currencyCode);
        $feeAmount = $this->formatPrice($this->helper->getSmallOrderFeeAmount(), $currencyCode);

        return str_replace(['%1', '%2'], [$minAmount, $feeAmount], $message);
    }

    public function formatPrice(float $amount, string $currencyCode = ''): string
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getStore()) {
            return $quote->getStore()->getCurrentCurrency()->format($amount, [], false);
        }

        return number_format($amount, 2);
    }

    public function getHelper(): ExtraFeeHelper
    {
        return $this->helper;
    }

    public function getEscaper(): \Magento\Framework\Escaper
    {
        return $this->_escaper;
    }
}
