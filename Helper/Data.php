<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH = 'panth_extra_fee/';

    public function __construct(
        Context $context,
        private readonly State $appState
    ) {
        parent::__construct($context);
    }

    public function isApplyToAdminOrders(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('general/apply_to_admin_orders', $storeId);
    }

    public function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function shouldApplyFees(?int $storeId = null): bool
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }
        if ($this->isAdminArea() && !$this->isApplyToAdminOrders($storeId)) {
            return false;
        }
        return true;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('general/enabled', $storeId);
    }

    public function getConfigValue(string $field, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isShowInCart(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_cart');
    }

    public function isShowInCheckout(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_checkout');
    }

    public function isShowInOrderView(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_view');
    }

    public function isShowInInvoice(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_invoice');
    }

    public function isShowInCreditmemo(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_creditmemo');
    }

    public function isShowInEmail(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_email');
    }

    public function isShowInOrderGrid(): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_grid');
    }

    public function getTaxDisplay(): int
    {
        return (int) $this->getConfigValue('display/tax_display');
    }

    public function isShowFeeBreakdown(): bool
    {
        return (bool) $this->getConfigValue('display/show_fee_breakdown');
    }

    public function isShowZeroFees(): bool
    {
        return (bool) $this->getConfigValue('display/show_zero_fees');
    }

    public function isSmallOrderFeeEnabled(): bool
    {
        return (bool) $this->getConfigValue('small_order/enabled');
    }

    public function getSmallOrderMinAmount(): float
    {
        return (float) $this->getConfigValue('small_order/minimum_amount');
    }

    public function getSmallOrderFeeType(): string
    {
        return (string) $this->getConfigValue('small_order/fee_type');
    }

    public function getSmallOrderFeeAmount(): float
    {
        return (float) $this->getConfigValue('small_order/fee_amount');
    }

    public function getSmallOrderFeeLabel(): string
    {
        return (string) $this->getConfigValue('small_order/fee_label');
    }

    public function getSmallOrderTaxClassId(): int
    {
        return (int) $this->getConfigValue('small_order/tax_class_id');
    }

    public function getSmallOrderMessage(): string
    {
        return (string) $this->getConfigValue('small_order/message');
    }

    public function isApplyAfterDiscount(): bool
    {
        return (bool) $this->getConfigValue('advanced/apply_after_discount');
    }

    public function getMaxTotalFee(): ?float
    {
        $value = $this->getConfigValue('advanced/maximum_fee');
        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    public function isExcludeVirtualProducts(): bool
    {
        return (bool) $this->getConfigValue('advanced/exclude_virtual');
    }

    public function isDebugMode(): bool
    {
        return (bool) $this->getConfigValue('advanced/debug_mode');
    }

    public function getFeeDisplayTitle(): string
    {
        return (string) $this->getConfigValue('general/fee_display_title');
    }
}
