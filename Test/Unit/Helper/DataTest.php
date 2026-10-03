<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Panth\ExtraFee\Helper\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    private array $calls = [];

    private function helper(array $values, ?string $area = Area::AREA_FRONTEND, bool $areaThrows = false): Data
    {
        $this->calls = [];
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope, $storeId) use ($values) {
                $this->calls[] = [$path, $scope, $storeId];
                return $values[$path] ?? null;
            }
        );
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        $state = $this->createStub(State::class);
        if ($areaThrows) {
            $state->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));
        } else {
            $state->method('getAreaCode')->willReturn($area);
        }

        return new Data($context, $state);
    }

    public function testConfigValueIsReadFromTheModuleSectionAtStoreScope(): void
    {
        $helper = $this->helper(['panth_extra_fee/general/enabled' => '1']);

        $this->assertTrue($helper->isEnabled(4));
        $this->assertSame(['panth_extra_fee/general/enabled', ScopeInterface::SCOPE_STORE, 4], $this->calls[0]);
    }

    public static function booleanFlags(): array
    {
        return [
            ['isApplyToAdminOrders', 'general/apply_to_admin_orders'],
            ['isShowInCart', 'display/show_in_cart'],
            ['isShowInCheckout', 'display/show_in_checkout'],
            ['isShowInOrderView', 'display/show_in_order_view'],
            ['isShowInInvoice', 'display/show_in_invoice'],
            ['isShowInCreditmemo', 'display/show_in_creditmemo'],
            ['isShowInEmail', 'display/show_in_email'],
            ['isShowInOrderGrid', 'display/show_in_order_grid'],
            ['isShowFeeBreakdown', 'display/show_fee_breakdown'],
            ['isShowZeroFees', 'display/show_zero_fees'],
            ['isSmallOrderFeeEnabled', 'small_order/enabled'],
            ['isApplyAfterDiscount', 'advanced/apply_after_discount'],
            ['isChargeFeeTax', 'advanced/charge_fee_tax'],
            ['isExcludeVirtualProducts', 'advanced/exclude_virtual'],
            ['isDebugMode', 'advanced/debug_mode'],
        ];
    }

    #[DataProvider('booleanFlags')]
    public function testBooleanFlagsFollowTheirConfigPath(string $method, string $field): void
    {
        $this->assertTrue($this->helper(['panth_extra_fee/' . $field => '1'])->$method(1));
        $this->assertFalse($this->helper(['panth_extra_fee/' . $field => '0'])->$method(1));
        $this->assertFalse($this->helper([])->$method(1));
    }

    public function testTypedSmallOrderValues(): void
    {
        $helper = $this->helper([
            'panth_extra_fee/small_order/minimum_amount' => '25.5',
            'panth_extra_fee/small_order/fee_type' => 'percent',
            'panth_extra_fee/small_order/fee_amount' => '3',
            'panth_extra_fee/small_order/fee_label' => 'Tiny order',
            'panth_extra_fee/small_order/tax_class_id' => '2',
            'panth_extra_fee/small_order/message' => 'Spend %1 to avoid %2',
            'panth_extra_fee/display/tax_display' => '3',
            'panth_extra_fee/general/fee_display_title' => 'Fees',
        ]);

        $this->assertSame(25.5, $helper->getSmallOrderMinAmount());
        $this->assertSame('percent', $helper->getSmallOrderFeeType());
        $this->assertSame(3.0, $helper->getSmallOrderFeeAmount());
        $this->assertSame('Tiny order', $helper->getSmallOrderFeeLabel());
        $this->assertSame(2, $helper->getSmallOrderTaxClassId());
        $this->assertSame('Spend %1 to avoid %2', $helper->getSmallOrderMessage());
        $this->assertSame(3, $helper->getTaxDisplay());
        $this->assertSame('Fees', $helper->getFeeDisplayTitle());
    }

    public function testUnsetValuesCastToEmptyDefaults(): void
    {
        $helper = $this->helper([]);

        $this->assertSame(0.0, $helper->getSmallOrderMinAmount());
        $this->assertSame('', $helper->getSmallOrderFeeType());
        $this->assertSame(0, $helper->getTaxDisplay());
        $this->assertSame('', $helper->getFeeDisplayTitle());
    }

    public function testMaxTotalFeeIsNullWhenUnsetOrEmpty(): void
    {
        $this->assertNull($this->helper([])->getMaxTotalFee());
        $this->assertNull($this->helper(['panth_extra_fee/advanced/maximum_fee' => ''])->getMaxTotalFee());
    }

    public function testMaxTotalFeeIsCastToFloatIncludingZero(): void
    {
        $this->assertSame(12.75, $this->helper(['panth_extra_fee/advanced/maximum_fee' => '12.75'])->getMaxTotalFee());
        $this->assertSame(0.0, $this->helper(['panth_extra_fee/advanced/maximum_fee' => '0'])->getMaxTotalFee());
    }

    public function testIsAdminAreaDetectsAdminhtml(): void
    {
        $this->assertTrue($this->helper([], Area::AREA_ADMINHTML)->isAdminArea());
        $this->assertFalse($this->helper([], Area::AREA_FRONTEND)->isAdminArea());
    }

    public function testIsAdminAreaIsFalseWhenAreaIsNotSet(): void
    {
        $this->assertFalse($this->helper([], null, true)->isAdminArea());
    }

    public function testShouldApplyFeesIsFalseWhenModuleDisabled(): void
    {
        $this->assertFalse($this->helper([], Area::AREA_FRONTEND)->shouldApplyFees(1));
    }

    public function testShouldApplyFeesOnTheStorefrontWhenEnabled(): void
    {
        $helper = $this->helper(['panth_extra_fee/general/enabled' => '1'], Area::AREA_FRONTEND);

        $this->assertTrue($helper->shouldApplyFees(1));
    }

    public function testAdminOrdersNeedTheirOwnSwitch(): void
    {
        $off = $this->helper(['panth_extra_fee/general/enabled' => '1'], Area::AREA_ADMINHTML);
        $on = $this->helper([
            'panth_extra_fee/general/enabled' => '1',
            'panth_extra_fee/general/apply_to_admin_orders' => '1',
        ], Area::AREA_ADMINHTML);

        $this->assertFalse($off->shouldApplyFees(1));
        $this->assertTrue($on->shouldApplyFees(1));
    }
}
