<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Calculator;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\Calculator\ConditionChecker;
use Panth\ExtraFee\Model\Calculator\FeeCalculator;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeeCalculatorCapTest extends TestCase
{
    private function capFees(array $fees, float $cap): array
    {
        $helper = $this->createStub(Helper::class);
        $helper->method('getMaxTotalFee')->willReturn($cap);
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnCallback(fn ($v) => round((float)$v, 2));

        $calculator = new FeeCalculator(
            $this->createStub(CollectionFactory::class),
            $this->createStub(ConditionChecker::class),
            $helper,
            $this->createStub(TaxCalculation::class),
            $this->createStub(LoggerInterface::class),
            $priceCurrency
        );
        $method = new \ReflectionMethod(FeeCalculator::class, 'applyGlobalMaxCap');

        return $method->invoke($calculator, $fees, 1);
    }

    private function fee(float $amount, array $items = []): array
    {
        return ['base_amount' => $amount, 'amount' => $amount, 'base_tax' => 0.0, 'tax' => 0.0, 'items' => $items];
    }

    public function testCappedTotalNeverExceedsTheCap(): void
    {
        $fees = $this->capFees([$this->fee(10), $this->fee(10), $this->fee(10)], 20.0);
        $sum = array_sum(array_column($fees, 'base_amount'));
        $this->assertEqualsWithDelta(20.0, $sum, 0.00001);
        $this->assertSame(6.67, $fees[0]['base_amount']);
        $this->assertSame(6.66, $fees[2]['base_amount']);
    }

    public function testFeesUnderTheCapAreUntouched(): void
    {
        $fees = $this->capFees([$this->fee(4), $this->fee(5)], 20.0);
        $this->assertSame([4.0, 5.0], array_column($fees, 'base_amount'));
    }

    public function testItemBreakdownIsScaledWithTheFee(): void
    {
        $fees = $this->capFees([$this->fee(10, [7 => 4.0, 8 => 6.0])], 5.0);
        $this->assertSame(5.0, $fees[0]['base_amount']);
        $this->assertSame([7 => 2.0, 8 => 3.0], $fees[0]['items']);
    }
}
