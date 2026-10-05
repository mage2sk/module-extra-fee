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

    public function isShowInCart(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_cart', $storeId);
    }

    public function isShowInCheckout(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_checkout', $storeId);
    }

    public function isShowInOrderView(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_view', $storeId);
    }

    public function isShowInInvoice(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_invoice', $storeId);
    }

    public function isShowInCreditmemo(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_creditmemo', $storeId);
    }

    public function isShowInEmail(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_email', $storeId);
    }

    public function isShowInOrderGrid(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_in_order_grid', $storeId);
    }

    public function getTaxDisplay(?int $storeId = null): int
    {
        return (int) $this->getConfigValue('display/tax_display', $storeId);
    }

    public function isShowFeeBreakdown(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_fee_breakdown', $storeId);
    }

    public function isShowZeroFees(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('display/show_zero_fees', $storeId);
    }

    public function isSmallOrderFeeEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('small_order/enabled', $storeId);
    }

    public function getSmallOrderMinAmount(?int $storeId = null): float
    {
        return (float) $this->getConfigValue('small_order/minimum_amount', $storeId);
    }

    public function getSmallOrderFeeType(?int $storeId = null): string
    {
        return (string) $this->getConfigValue('small_order/fee_type', $storeId);
    }

    public function getSmallOrderFeeAmount(?int $storeId = null): float
    {
        return (float) $this->getConfigValue('small_order/fee_amount', $storeId);
    }

    public function getSmallOrderFeeLabel(?int $storeId = null): string
    {
        return (string) $this->getConfigValue('small_order/fee_label', $storeId);
    }

    public function getSmallOrderTaxClassId(?int $storeId = null): int
    {
        return (int) $this->getConfigValue('small_order/tax_class_id', $storeId);
    }

    public function getSmallOrderMessage(?int $storeId = null): string
    {
        return (string) $this->getConfigValue('small_order/message', $storeId);
    }

    public function isApplyAfterDiscount(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('advanced/apply_after_discount', $storeId);
    }

    public function getMaxTotalFee(?int $storeId = null): ?float
    {
        $value = $this->getConfigValue('advanced/maximum_fee', $storeId);
        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    public function isChargeFeeTax(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('advanced/charge_fee_tax', $storeId);
    }

    public function isExcludeVirtualProducts(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('advanced/exclude_virtual', $storeId);
    }

    public function isDebugMode(?int $storeId = null): bool
    {
        return (bool) $this->getConfigValue('advanced/debug_mode', $storeId);
    }

    public function getFeeDisplayTitle(?int $storeId = null): string
    {
        return (string) $this->getConfigValue('general/fee_display_title', $storeId);
    }
}
