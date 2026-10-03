<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model;

use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

class FeeRuleTest extends TestCase
{
    use ObjectHelperTrait;

    private function rule(array $data): FeeRule
    {
        return $this->newWithoutConstructor(FeeRule::class, $data);
    }

    public function testListFieldsAreSplitIntoArrays(): void
    {
        $rule = $this->rule([
            'payment_methods' => 'checkmo,cashondelivery',
            'customer_groups' => '1,2',
            'countries' => 'US,GB',
            'product_ids' => '10,11',
            'category_ids' => '3',
            'store_ids' => '1,2',
            'website_ids' => '1',
        ]);

        $this->assertSame(['checkmo', 'cashondelivery'], $rule->getPaymentMethodsArray());
        $this->assertSame(['1', '2'], $rule->getCustomerGroupsArray());
        $this->assertSame(['US', 'GB'], $rule->getCountriesArray());
        $this->assertSame(['10', '11'], $rule->getProductIdsArray());
        $this->assertSame(['3'], $rule->getCategoryIdsArray());
        $this->assertSame(['1', '2'], $rule->getStoreIdsArray());
        $this->assertSame(['1'], $rule->getWebsiteIdsArray());
    }

    public function testEmptyOrMissingListFieldsGiveEmptyArrays(): void
    {
        $rule = $this->rule(['payment_methods' => '', 'countries' => null]);

        $this->assertSame([], $rule->getPaymentMethodsArray());
        $this->assertSame([], $rule->getCountriesArray());
        $this->assertSame([], $rule->getWebsiteIdsArray());
    }

    public function testZeroIdsAreKept(): void
    {
        $rule = $this->rule(['customer_groups' => '0', 'store_ids' => '0,1']);

        $this->assertSame(['0'], $rule->getCustomerGroupsArray());
        $this->assertSame(['0', '1'], $rule->getStoreIdsArray());
    }

    public function testEmptySegmentsAreDropped(): void
    {
        $this->assertSame(['US', 'GB'], array_values($this->rule(['countries' => 'US,,GB,'])->getCountriesArray()));
    }

    public function testNumericGettersCastStoredStrings(): void
    {
        $rule = $this->rule([
            'rule_id' => '7',
            'fee_amount' => '2.50',
            'fee_amount_percent' => '1.5',
            'min_fee_amount' => '1',
            'max_fee_amount' => '9.99',
            'tax_class_id' => '2',
            'min_order_qty' => '3',
            'sort_order' => '20',
            'is_active' => '1',
            'stop_further_rules' => '0',
            'is_refundable' => '1',
        ]);

        $this->assertSame(7, $rule->getRuleId());
        $this->assertSame(2.5, $rule->getFeeAmount());
        $this->assertSame(1.5, $rule->getFeeAmountPercent());
        $this->assertSame(1.0, $rule->getMinFeeAmount());
        $this->assertSame(9.99, $rule->getMaxFeeAmount());
        $this->assertSame(2, $rule->getTaxClassId());
        $this->assertSame(3, $rule->getMinOrderQty());
        $this->assertSame(20, $rule->getSortOrder());
        $this->assertTrue($rule->getIsActive());
        $this->assertFalse($rule->getStopFurtherRules());
        $this->assertTrue($rule->getIsRefundable());
    }

    public function testNullableNumericGettersStayNullWhenUnset(): void
    {
        $rule = $this->rule([]);

        $this->assertNull($rule->getRuleId());
        $this->assertNull($rule->getMinFeeAmount());
        $this->assertNull($rule->getMaxOrderSubtotal());
        $this->assertNull($rule->getMaxOrderQty());
        $this->assertNull($rule->getTaxClassId());
    }
}
