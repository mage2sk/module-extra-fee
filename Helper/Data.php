<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Extra Fee module configuration helper.
 */
class Data extends AbstractHelper
{
    private const XML_PATH = 'panth_extra_fee/';

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Check if module is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('general/enabled', $storeId);
    }

    /**
     * Get configuration value.
     *
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue(string $field, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if fees should be shown in cart.
     *
     * @return bool
     */
    public function isShowInCart(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_cart');
    }

    /**
     * Check if fees should be shown in checkout.
     *
     * @return bool
     */
    public function isShowInCheckout(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_checkout');
    }

    /**
     * Check if fees should be shown in order view.
     *
     * @return bool
     */
    public function isShowInOrderView(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_view');
    }

    /**
     * Check if fees should be shown in invoice.
     *
     * @return bool
     */
    public function isShowInInvoice(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_invoice');
    }

    /**
     * Check if fees should be shown in credit memo.
     *
     * @return bool
     */
    public function isShowInCreditmemo(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_creditmemo');
    }

    /**
     * Check if fees should be shown in email.
     *
     * @return bool
     */
    public function isShowInEmail(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_email');
    }

    /**
     * Check if fees should be shown in order grid.
     *
     * @return bool
     */
    public function isShowInOrderGrid(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_grid');
    }

    /**
     * Get tax display type (1 = Excl, 2 = Incl, 3 = Both).
     *
     * @return int
     */
    public function getTaxDisplay(): int
    {
        return (int) $this->getConfigValue('display/tax_display');
    }

    /**
     * Check if fee breakdown should be shown.
     *
     * @return bool
     */
    public function isShowFeeBreakdown(): bool
    {
        return (bool) $this->getConfigValue('display/show_fee_breakdown');
    }

    /**
     * Check if zero fees should be shown.
     *
     * @return bool
     */
    public function isShowZeroFees(): bool
    {
        return (bool) $this->getConfigValue('display/show_zero_fees');
    }

    /**
     * Check if small order fee is enabled.
     *
     * @return bool
     */
    public function isSmallOrderFeeEnabled(): bool
    {
        return (bool) $this->getConfigValue('small_order/enabled');
    }

    /**
     * Get small order minimum amount threshold.
     *
     * @return float
     */
    public function getSmallOrderMinAmount(): float
    {
        return (float) $this->getConfigValue('small_order/minimum_amount');
    }

    /**
     * Get small order fee type.
     *
     * @return string
     */
    public function getSmallOrderFeeType(): string
    {
        return (string) $this->getConfigValue('small_order/fee_type');
    }

    /**
     * Get small order fee amount.
     *
     * @return float
     */
    public function getSmallOrderFeeAmount(): float
    {
        return (float) $this->getConfigValue('small_order/fee_amount');
    }

    /**
     * Get small order fee label.
     *
     * @return string
     */
    public function getSmallOrderFeeLabel(): string
    {
        return (string) $this->getConfigValue('small_order/fee_label');
    }

    /**
     * Get small order tax class ID.
     *
     * @return int
     */
    public function getSmallOrderTaxClassId(): int
    {
        return (int) $this->getConfigValue('small_order/tax_class_id');
    }

    /**
     * Get small order message with %1 and %2 replacements.
     *
     * @return string
     */
    public function getSmallOrderMessage(): string
    {
        return (string) $this->getConfigValue('small_order/message');
    }

    /**
     * Check if fees should be applied after discount.
     *
     * @return bool
     */
    public function isApplyAfterDiscount(): bool
    {
        return (bool) $this->getConfigValue('advanced/apply_after_discount');
    }

    /**
     * Get maximum total fee cap per order.
     *
     * @return float|null
     */
    public function getMaxTotalFee(): ?float
    {
        $value = $this->getConfigValue('advanced/maximum_fee');
        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    /**
     * Check if virtual products should be excluded.
     *
     * @return bool
     */
    public function isExcludeVirtualProducts(): bool
    {
        return (bool) $this->getConfigValue('advanced/exclude_virtual');
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool) $this->getConfigValue('advanced/debug_mode');
    }

    /**
     * Get the fee display title.
     *
     * @return string
     */
    public function getFeeDisplayTitle(): string
    {
        return (string) $this->getConfigValue('general/fee_display_title');
    }
}
