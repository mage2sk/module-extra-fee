<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Calculator;

use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\ExtraFee\Model\Calculator\ConditionChecker;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\Test\Unit\Fixture\QuoteDouble;
use PHPUnit\Framework\TestCase;

class ConditionCheckerTest extends TestCase
{
    use ObjectHelperTrait;

    private function checker(string $today = '2026-06-15'): ConditionChecker
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('scopeDate')->willReturn(new \DateTime($today));
        return new ConditionChecker($timezone);
    }

    private function rule(array $data = []): FeeRule
    {
        return $this->newWithoutConstructor(FeeRule::class, $data + ['is_active' => 1]);
    }

    private function quote(array $data = [], array $items = []): QuoteDouble
    {
        $quote = new QuoteDouble($data + ['store_id' => 1, 'customer_group_id' => 1, 'items_qty' => 2]);
        $quote->storeDouble = new DataObject(['website_id' => 1]);
        $quote->shippingDouble = new DataObject(['country_id' => 'US', 'region_id' => 12, 'base_subtotal' => 100]);
        $quote->billingDouble = new DataObject(['country_id' => 'GB', 'region_id' => 40, 'base_subtotal' => 0]);
        $quote->paymentDouble = new DataObject(['method' => 'checkmo']);
        $quote->visibleItems = $items;
        return $quote;
    }

    private function item(int $productId, string $sku, array $categoryIds = []): DataObject
    {
        return new DataObject([
            'product_id' => $productId,
            'sku' => $sku,
            'product' => new DataObject(['category_ids' => $categoryIds]),
        ]);
    }

    public function testRuleWithoutConditionsIsValid(): void
    {
        $this->assertTrue($this->checker()->isRuleValid($this->rule(), $this->quote()));
    }

    public function testInactiveRuleIsNeverValid(): void
    {
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['is_active' => 0]), $this->quote()));
    }

    public function testStoreRestriction(): void
    {
        $this->assertTrue($this->checker()->isRuleValid($this->rule(['store_ids' => '1,2']), $this->quote()));
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['store_ids' => '2,3']), $this->quote()));
    }

    public function testWebsiteRestriction(): void
    {
        $this->assertTrue($this->checker()->isRuleValid($this->rule(['website_ids' => '1']), $this->quote()));
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['website_ids' => '2']), $this->quote()));
    }

    public function testDateWindowIsInclusive(): void
    {
        $checker = $this->checker('2026-06-15');

        $this->assertTrue($checker->isRuleValid(
            $this->rule(['date_from' => '2026-06-15', 'date_to' => '2026-06-15']),
            $this->quote()
        ));
        $this->assertFalse($checker->isRuleValid($this->rule(['date_from' => '2026-06-16']), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['date_to' => '2026-06-14']), $this->quote()));
        $this->assertTrue($checker->isRuleValid($this->rule(['date_from' => '', 'date_to' => '']), $this->quote()));
    }

    public function testCustomerGroupRestriction(): void
    {
        $this->assertTrue($this->checker()->isRuleValid($this->rule(['customer_groups' => '1,2']), $this->quote()));
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['customer_groups' => '2,3']), $this->quote()));
    }

    public function testNotLoggedInGroupAndAllStoresAreHonoured(): void
    {
        $checker = $this->checker();

        $this->assertFalse($checker->isRuleValid($this->rule(['customer_groups' => '0']), $this->quote()));
        $this->assertTrue($checker->isRuleValid(
            $this->rule(['customer_groups' => '0']),
            $this->quote(['customer_group_id' => 0])
        ));
        $this->assertTrue($checker->isRuleValid($this->rule(['store_ids' => '0,5']), $this->quote()));
    }

    public function testPaymentMethodRestriction(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['payment_methods' => 'checkmo']), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['payment_methods' => 'free']), $this->quote()));
    }

    public function testPaymentRestrictedRuleFailsWhenNoMethodChosenYet(): void
    {
        $quote = $this->quote();
        $quote->paymentDouble = new DataObject(['method' => '']);

        $this->assertFalse($this->checker()->isRuleValid($this->rule(['payment_methods' => 'checkmo']), $quote));

        $quote->paymentDouble = null;
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['payment_methods' => 'checkmo']), $quote));
    }

    public function testCountryMatchesShippingOrBilling(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['countries' => 'US']), $this->quote()));
        $this->assertTrue($checker->isRuleValid($this->rule(['countries' => 'GB']), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['countries' => 'FR,DE']), $this->quote()));
    }

    public function testCountryRestrictionFailsWithoutAddresses(): void
    {
        $quote = $this->quote();
        $quote->shippingDouble = null;
        $quote->billingDouble = null;

        $this->assertFalse($this->checker()->isRuleValid($this->rule(['countries' => 'US']), $quote));
    }

    public function testRegionListIsTrimmedAndMatchesEitherAddress(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['regions' => ' 99 , 12 ']), $this->quote()));
        $this->assertTrue($checker->isRuleValid($this->rule(['regions' => '40']), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['regions' => '1,2']), $this->quote()));
    }

    public function testSubtotalBounds(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['min_order_subtotal' => 100]), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['min_order_subtotal' => 100.01]), $this->quote()));
        $this->assertTrue($checker->isRuleValid($this->rule(['max_order_subtotal' => 100]), $this->quote()));
        $this->assertFalse($checker->isRuleValid($this->rule(['max_order_subtotal' => 99.99]), $this->quote()));
    }

    public function testZeroSubtotalBoundsAreIgnored(): void
    {
        $this->assertTrue($this->checker()->isRuleValid(
            $this->rule(['min_order_subtotal' => 0, 'max_order_subtotal' => 0]),
            $this->quote()
        ));
    }

    public function testVirtualQuoteUsesBillingSubtotalAndFallsBackToQuote(): void
    {
        $quote = $this->quote(['base_subtotal' => 30]);
        $quote->virtual = true;

        $this->assertTrue($this->checker()->isRuleValid($this->rule(['max_order_subtotal' => 50]), $quote));
        $this->assertFalse($this->checker()->isRuleValid($this->rule(['min_order_subtotal' => 50]), $quote));
    }

    public function testQuantityBounds(): void
    {
        $checker = $this->checker();
        $quote = $this->quote(['items_qty' => 5]);

        $this->assertTrue($checker->isRuleValid($this->rule(['min_order_qty' => 5, 'max_order_qty' => 5]), $quote));
        $this->assertFalse($checker->isRuleValid($this->rule(['min_order_qty' => 6]), $quote));
        $this->assertFalse($checker->isRuleValid($this->rule(['max_order_qty' => 4]), $quote));
    }

    public function testProductConditionMatchesByIdOrSku(): void
    {
        $quote = $this->quote([], [$this->item(10, 'shirt'), $this->item(11, 'hat')]);
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['product_ids' => '11']), $quote));
        $this->assertTrue($checker->isRuleValid($this->rule(['product_skus' => 'mug, hat']), $quote));
        $this->assertFalse($checker->isRuleValid($this->rule(['product_ids' => '99', 'product_skus' => 'mug']), $quote));
    }

    public function testCategoryConditionNeedsAnItemInTheCategory(): void
    {
        $quote = $this->quote([], [$this->item(10, 'shirt', ['3', '5']), $this->item(11, 'hat', [])]);
        $checker = $this->checker();

        $this->assertTrue($checker->isRuleValid($this->rule(['category_ids' => '5']), $quote));
        $this->assertFalse($checker->isRuleValid($this->rule(['category_ids' => '8']), $quote));
    }

    public function testCategoryConditionSkipsItemsWithoutProduct(): void
    {
        $quote = $this->quote([], [new DataObject(['product_id' => 1, 'sku' => 'x'])]);

        $this->assertFalse($this->checker()->isRuleValid($this->rule(['category_ids' => '5']), $quote));
    }
}
