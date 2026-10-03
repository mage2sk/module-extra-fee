<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Calculator;

use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\Calculator\ConditionChecker;
use Panth\ExtraFee\Model\Calculator\FeeCalculator;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\Test\Unit\Fixture\QuoteDouble;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeeCalculatorTest extends TestCase
{
    use ObjectHelperTrait;

    private array $config = [];

    private array $rules = [];

    private array $validity = [];

    private float $taxRate = 0.0;

    private float $currencyRate = 1.0;

    private ?\Exception $taxException = null;

    protected function setUp(): void
    {
        $this->config = [
            'isEnabled' => true,
            'isShowZeroFees' => false,
            'isSmallOrderFeeEnabled' => false,
            'isApplyAfterDiscount' => false,
            'isExcludeVirtualProducts' => false,
            'isDebugMode' => false,
            'getMaxTotalFee' => null,
            'getSmallOrderMinAmount' => 0.0,
            'getSmallOrderFeeType' => 'fixed',
            'getSmallOrderFeeAmount' => 0.0,
            'getSmallOrderTaxClassId' => 0,
            'getSmallOrderFeeLabel' => '',
        ];
    }

    private function calculator(?LoggerInterface $logger = null): FeeCalculator
    {
        $helper = $this->createStub(Helper::class);
        foreach ($this->config as $method => $value) {
            $helper->method($method)->willReturnCallback(fn() => $this->config[$method]);
        }

        $collection = $this->collectionOf(Collection::class, $this->rules);
        $collection->method('addActiveFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $factory = $this->factoryReturning(CollectionFactory::class, $collection);

        $checker = $this->createStub(ConditionChecker::class);
        $checker->method('isRuleValid')->willReturnCallback(
            fn(FeeRule $rule) => $this->validity[$rule->getRuleId()] ?? true
        );

        $tax = $this->createStub(TaxCalculation::class);
        $tax->method('getRateRequest')->willReturnCallback(function () {
            if ($this->taxException) {
                throw $this->taxException;
            }
            return new DataObject();
        });
        $tax->method('getRate')->willReturnCallback(fn() => $this->taxRate);

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnCallback(static fn($v) => round((float)$v, 2));
        $priceCurrency->method('convert')->willReturnCallback(fn($v) => (float)$v * $this->currencyRate);

        return new FeeCalculator(
            $factory,
            $checker,
            $helper,
            $tax,
            $logger ?? $this->createStub(LoggerInterface::class),
            $priceCurrency
        );
    }

    private function rule(array $data): FeeRule
    {
        return $this->newWithoutConstructor(FeeRule::class, $data + [
            'rule_id' => 1,
            'name' => 'Rule',
            'fee_label' => 'Handling',
            'is_active' => 1,
        ]);
    }

    private function item(int $id, int $productId, string $sku, float $qty, array $extra = []): DataObject
    {
        return new DataObject($extra + [
            'id' => $id,
            'product_id' => $productId,
            'sku' => $sku,
            'qty' => $qty,
            'product' => new DataObject(['category_ids' => $extra['category_ids'] ?? []]),
        ]);
    }

    private function quote(float $subtotal = 100.0, array $items = [], array $data = []): QuoteDouble
    {
        $quote = new QuoteDouble($data + [
            'store_id' => 1,
            'items_count' => max(1, count($items)),
            'quote_currency_code' => 'EUR',
        ]);
        $quote->storeDouble = new DataObject(['id' => 1]);
        $quote->shippingDouble = new DataObject([
            'base_subtotal' => $subtotal,
            'base_subtotal_with_discount' => $subtotal - 20,
        ]);
        $quote->billingDouble = new DataObject();
        $quote->visibleItems = $items;
        return $quote;
    }

    public function testDisabledModuleProducesNoFees(): void
    {
        $this->config['isEnabled'] = false;
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 5])];

        $this->assertSame([], $this->calculator()->calculateFees($this->quote()));
    }

    public function testEmptyQuoteProducesNoFees(): void
    {
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 5])];

        $this->assertSame([], $this->calculator()->calculateFees($this->quote(100, [], ['items_count' => 0])));
    }

    public function testFixedOrderFeeIsReturnedWithRuleDetails(): void
    {
        $this->rules = [$this->rule(['rule_id' => 9, 'fee_type' => 'fixed', 'fee_amount' => 5, 'apply_per' => 'order'])];

        $fees = $this->calculator()->calculateFees($this->quote());

        $this->assertCount(1, $fees);
        $this->assertSame(9, $fees[0]['rule_id']);
        $this->assertSame('Handling', $fees[0]['label']);
        $this->assertSame('fixed', $fees[0]['fee_type']);
        $this->assertSame(5.0, $fees[0]['base_amount']);
        $this->assertSame(5.0, $fees[0]['amount']);
        $this->assertSame([], $fees[0]['items']);
    }

    public function testAmountsAreConvertedToQuoteCurrency(): void
    {
        $this->currencyRate = 2.0;
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 5])];

        $fee = $this->calculator()->calculateFees($this->quote())[0];

        $this->assertSame(5.0, $fee['base_amount']);
        $this->assertSame(10.0, $fee['amount']);
    }

    public function testRulesWhoseConditionsFailAreSkipped(): void
    {
        $this->rules = [
            $this->rule(['rule_id' => 1, 'fee_type' => 'fixed', 'fee_amount' => 5]),
            $this->rule(['rule_id' => 2, 'fee_type' => 'fixed', 'fee_amount' => 7]),
        ];
        $this->validity = [1 => false];

        $fees = $this->calculator()->calculateFees($this->quote());

        $this->assertSame([2], array_column($fees, 'rule_id'));
    }

    public function testStopFurtherRulesEndsProcessing(): void
    {
        $this->rules = [
            $this->rule(['rule_id' => 1, 'fee_type' => 'fixed', 'fee_amount' => 5, 'stop_further_rules' => 1]),
            $this->rule(['rule_id' => 2, 'fee_type' => 'fixed', 'fee_amount' => 7]),
        ];

        $this->assertSame([1], array_column($this->calculator()->calculateFees($this->quote()), 'rule_id'));
    }

    public function testZeroFeesAreHiddenUnlessConfiguredToShow(): void
    {
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 0])];

        $this->assertSame([], $this->calculator()->calculateFees($this->quote()));

        $this->config['isShowZeroFees'] = true;
        $fees = $this->calculator()->calculateFees($this->quote());
        $this->assertCount(1, $fees);
        $this->assertSame(0.0, $fees[0]['base_amount']);
    }

    public function testMinAndMaxFeeConstraintsClampTheAmount(): void
    {
        $this->rules = [
            $this->rule(['rule_id' => 1, 'fee_type' => 'fixed', 'fee_amount' => 1, 'min_fee_amount' => 3]),
            $this->rule(['rule_id' => 2, 'fee_type' => 'fixed', 'fee_amount' => 50, 'max_fee_amount' => 10]),
        ];

        $fees = $this->calculator()->calculateFees($this->quote());

        $this->assertSame([3.0, 10.0], array_column($fees, 'base_amount'));
    }

    public function testMinimumFeeLiftsAZeroFeeAboveZero(): void
    {
        $this->rules = [$this->rule(['fee_type' => 'percent', 'fee_amount_percent' => 0, 'min_fee_amount' => 2])];

        $this->assertSame([2.0], array_column($this->calculator()->calculateFees($this->quote()), 'base_amount'));
    }

    public function testTaxIsAddedForTaxableRules(): void
    {
        $this->taxRate = 20.0;
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 10, 'tax_class_id' => 2])];

        $fee = $this->calculator()->calculateFees($this->quote())[0];

        $this->assertSame(2.0, $fee['base_tax']);
        $this->assertSame(2.0, $fee['tax']);
    }

    public function testPercentFeeUsesSubtotal(): void
    {
        $rule = $this->rule(['fee_type' => 'percent', 'fee_amount_percent' => 2.5]);

        $this->assertSame(2.5, $this->calculator()->calculateAmount($rule, $this->quote(100)));
    }

    public function testPercentFeeUsesDiscountedSubtotalWhenConfigured(): void
    {
        $this->config['isApplyAfterDiscount'] = true;
        $rule = $this->rule(['fee_type' => 'percent', 'fee_amount_percent' => 10]);

        $this->assertSame(8.0, $this->calculator()->calculateAmount($rule, $this->quote(100)));
    }

    public function testSubtotalFallsBackToQuoteWhenAddressHasNone(): void
    {
        $quote = $this->quote(0, [], ['base_subtotal' => 40]);
        $rule = $this->rule(['fee_type' => 'percent', 'fee_amount_percent' => 10]);

        $this->assertSame(4.0, $this->calculator()->calculateAmount($rule, $quote));
    }

    public function testVirtualQuoteReadsTheBillingAddress(): void
    {
        $quote = $this->quote(100);
        $quote->virtual = true;
        $quote->billingDouble = new DataObject(['base_subtotal' => 50]);
        $rule = $this->rule(['fee_type' => 'percent', 'fee_amount_percent' => 10]);

        $this->assertSame(5.0, $this->calculator()->calculateAmount($rule, $quote));
    }

    public function testCombinedFeeAddsFixedAndPercent(): void
    {
        $rule = $this->rule(['fee_type' => 'combined', 'fee_amount' => 2, 'fee_amount_percent' => 1]);

        $this->assertSame(3.0, $this->calculator()->calculateAmount($rule, $this->quote(100)));
    }

    public function testFixedMinimumTakesTheLargerPart(): void
    {
        $rule = $this->rule(['fee_type' => 'fixed_minimum', 'fee_amount' => 10, 'fee_amount_percent' => 1.5]);

        $this->assertSame(10.0, $this->calculator()->calculateAmount($rule, $this->quote(100)));
        $this->assertSame(15.0, $this->calculator()->calculateAmount($rule, $this->quote(1000)));
    }

    public function testUnknownFeeTypeIsLoggedAndZero(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('bogus'));

        $rule = $this->rule(['fee_type' => 'bogus', 'fee_amount' => 5]);

        $this->assertSame(0.0, $this->calculator($logger)->calculateAmount($rule, $this->quote()));
    }

    public function testPerProductFeeCountsMatchingLines(): void
    {
        $items = [
            $this->item(1, 10, 'shirt', 3),
            $this->item(2, 11, 'hat', 1),
            $this->item(3, 12, 'mug', 2, ['category_ids' => ['7']]),
        ];
        $quote = $this->quote(100, $items);
        $calculator = $this->calculator();

        $all = $this->rule(['fee_type' => 'fixed', 'fee_amount' => 2, 'apply_per' => 'product']);
        $this->assertSame(6.0, $calculator->calculateAmount($all, $quote));

        $byCondition = $this->rule([
            'fee_type' => 'fixed',
            'fee_amount' => 2,
            'apply_per' => 'product',
            'product_ids' => '10',
            'product_skus' => 'hat',
            'category_ids' => '7',
        ]);
        $this->assertSame(6.0, $calculator->calculateAmount($byCondition, $quote));

        $onlyShirt = $this->rule(['fee_type' => 'fixed', 'fee_amount' => 2, 'apply_per' => 'product', 'product_ids' => '10']);
        $this->assertSame(2.0, $calculator->calculateAmount($onlyShirt, $quote));
    }

    public function testPerQuantityFeeSumsMatchingQty(): void
    {
        $items = [$this->item(1, 10, 'shirt', 3), $this->item(2, 11, 'hat', 1)];
        $rule = $this->rule(['fee_type' => 'fixed', 'fee_amount' => 0.5, 'apply_per' => 'quantity', 'product_skus' => 'shirt']);

        $this->assertSame(1.5, $this->calculator()->calculateAmount($rule, $this->quote(100, $items)));
    }

    public function testVirtualItemsAreExcludedWhenConfigured(): void
    {
        $this->config['isExcludeVirtualProducts'] = true;
        $items = [$this->item(1, 10, 'ebook', 2, ['is_virtual' => true]), $this->item(2, 11, 'hat', 1)];
        $rule = $this->rule(['fee_type' => 'fixed', 'fee_amount' => 1, 'apply_per' => 'quantity']);

        $this->assertSame(1.0, $this->calculator()->calculateAmount($rule, $this->quote(100, $items)));
    }

    public function testPerProductFeeIsSplitAcrossLinesEvenly(): void
    {
        $items = [$this->item(5, 10, 'shirt', 3), $this->item(6, 11, 'hat', 1)];
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 2, 'apply_per' => 'product'])];

        $fee = $this->calculator()->calculateFees($this->quote(100, $items))[0];

        $this->assertSame(4.0, $fee['base_amount']);
        $this->assertSame([5 => 2.0, 6 => 2.0], $fee['items']);
    }

    public function testPerQuantityFeeIsSplitByQty(): void
    {
        $items = [$this->item(5, 10, 'shirt', 3), $this->item(6, 11, 'hat', 1)];
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 1, 'apply_per' => 'quantity'])];

        $fee = $this->calculator()->calculateFees($this->quote(100, $items))[0];

        $this->assertSame([5 => 3.0, 6 => 1.0], $fee['items']);
    }

    public function testBreakdownIsSkippedForOrderAndPercentFees(): void
    {
        $items = [$this->item(5, 10, 'shirt', 1)];
        $this->rules = [
            $this->rule(['rule_id' => 1, 'fee_type' => 'fixed', 'fee_amount' => 1, 'apply_per' => 'order']),
            $this->rule(['rule_id' => 2, 'fee_type' => 'percent', 'fee_amount_percent' => 5, 'apply_per' => 'product']),
        ];

        $fees = $this->calculator()->calculateFees($this->quote(100, $items));

        $this->assertSame([[], []], array_column($fees, 'items'));
    }

    public function testBreakdownIsDroppedWhenAnItemIsNotSavedYet(): void
    {
        $items = [$this->item(5, 10, 'shirt', 1), $this->item(0, 11, 'hat', 1)];
        $this->rules = [$this->rule(['fee_type' => 'fixed', 'fee_amount' => 1, 'apply_per' => 'product'])];

        $fee = $this->calculator()->calculateFees($this->quote(100, $items))[0];

        $this->assertSame(2.0, $fee['base_amount']);
        $this->assertSame([], $fee['items']);
    }

    public function testGlobalCapScalesRuleFees(): void
    {
        $this->config['getMaxTotalFee'] = 6.0;
        $this->rules = [
            $this->rule(['rule_id' => 1, 'fee_type' => 'fixed', 'fee_amount' => 6]),
            $this->rule(['rule_id' => 2, 'fee_type' => 'fixed', 'fee_amount' => 6]),
        ];

        $fees = $this->calculator()->calculateFees($this->quote());

        $this->assertSame([3.0, 3.0], array_column($fees, 'base_amount'));
    }

    public function testSmallOrderFeeIsAddedBeforeRuleFees(): void
    {
        $this->config['isSmallOrderFeeEnabled'] = true;
        $this->config['getSmallOrderMinAmount'] = 50.0;
        $this->config['getSmallOrderFeeAmount'] = 4.0;
        $this->rules = [$this->rule(['rule_id' => 3, 'fee_type' => 'fixed', 'fee_amount' => 1])];

        $fees = $this->calculator()->calculateFees($this->quote(30));

        $this->assertSame([0, 3], array_column($fees, 'rule_id'));
        $this->assertSame('small_order', $fees[0]['fee_type']);
        $this->assertSame(4.0, $fees[0]['base_amount']);
    }

    public function testSmallOrderFeeIsNullWhenDisabled(): void
    {
        $this->assertNull($this->calculator()->calculateSmallOrderFee($this->quote(10)));
    }

    public function testSmallOrderFeeIsNullAtOrAboveTheMinimum(): void
    {
        $this->config['isSmallOrderFeeEnabled'] = true;
        $this->config['getSmallOrderMinAmount'] = 50.0;
        $this->config['getSmallOrderFeeAmount'] = 4.0;

        $this->assertNull($this->calculator()->calculateSmallOrderFee($this->quote(50)));
    }

    public function testSmallOrderFeeIsNullWhenAmountIsZero(): void
    {
        $this->config['isSmallOrderFeeEnabled'] = true;
        $this->config['getSmallOrderMinAmount'] = 50.0;

        $this->assertNull($this->calculator()->calculateSmallOrderFee($this->quote(10)));
    }

    public function testSmallOrderPercentFeeWithTaxAndCustomLabel(): void
    {
        $this->config['isSmallOrderFeeEnabled'] = true;
        $this->config['getSmallOrderMinAmount'] = 50.0;
        $this->config['getSmallOrderFeeType'] = 'percent';
        $this->config['getSmallOrderFeeAmount'] = 10.0;
        $this->config['getSmallOrderTaxClassId'] = 2;
        $this->config['getSmallOrderFeeLabel'] = 'Tiny basket';
        $this->taxRate = 10.0;

        $fee = $this->calculator()->calculateSmallOrderFee($this->quote(20));

        $this->assertSame('Tiny basket', $fee['label']);
        $this->assertSame(2.0, $fee['base_amount']);
        $this->assertSame(0.2, $fee['base_tax']);
        $this->assertSame(0, $fee['rule_id']);
    }

    public function testSmallOrderFeeHasADefaultLabel(): void
    {
        $this->config['isSmallOrderFeeEnabled'] = true;
        $this->config['getSmallOrderMinAmount'] = 50.0;
        $this->config['getSmallOrderFeeAmount'] = 3.0;

        $this->assertSame('Small Order Fee', $this->calculator()->calculateSmallOrderFee($this->quote(20))['label']);
    }

    public function testTaxIsZeroForNonPositiveInputsOrRate(): void
    {
        $calculator = $this->calculator();
        $quote = $this->quote();

        $this->assertSame(0.0, $calculator->calculateTax(0.0, 2, $quote));
        $this->assertSame(0.0, $calculator->calculateTax(10.0, 0, $quote));
        $this->assertSame(0.0, $calculator->calculateTax(10.0, 2, $quote));
    }

    public function testTaxUsesTheCalculatedRate(): void
    {
        $this->taxRate = 7.5;

        $this->assertSame(1.5, $this->calculator()->calculateTax(20.0, 2, $this->quote()));
    }

    public function testTaxErrorsAreLoggedAndYieldZero(): void
    {
        $this->taxException = new \RuntimeException('rate lookup failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('rate lookup failed'));

        $this->assertSame(0.0, $this->calculator($logger)->calculateTax(20.0, 2, $this->quote()));
    }

    public function testDebugModeLogsSkippedRules(): void
    {
        $this->config['isDebugMode'] = true;
        $this->rules = [$this->rule(['rule_id' => 4, 'fee_type' => 'fixed', 'fee_amount' => 5])];
        $this->validity = [4 => false];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('debug')->with($this->stringContains('Rule #4'));

        $this->assertSame([], $this->calculator($logger)->calculateFees($this->quote()));
    }
}
