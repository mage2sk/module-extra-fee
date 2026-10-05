<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Pdf\Total;

use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\Config\Source\SmallOrderFeeType;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\Pdf\Total\ExtraFee;
use PHPUnit\Framework\TestCase;

class ExtraFeeTest extends TestCase
{
    private ?array $rows = [];

    private array $config = [];

    protected function setUp(): void
    {
        $this->config = [
            'getFeeDisplayTitle' => 'Additional Fees',
            'isShowFeeBreakdown' => true,
            'isShowZeroFees' => false,
            'getTaxDisplay' => 1,
        ];
    }

    private function total(?DataObject $source, bool $withOrder = true, ?string $prefix = null): ExtraFee
    {
        $documentTotals = $this->createStub(DocumentFeeTotals::class);
        $documentTotals->method('getRows')->willReturnCallback(fn() => $this->rows);

        $helper = $this->createStub(Helper::class);
        foreach (array_keys($this->config) as $method) {
            $helper->method($method)->willReturnCallback(fn() => $this->config[$method]);
        }

        $total = new ExtraFee(
            $this->createStub(TaxHelper::class),
            $this->createStub(Calculation::class),
            $this->createStub(CollectionFactory::class),
            $documentTotals,
            $helper
        );

        if ($withOrder) {
            $order = $this->createStub(Order::class);
            $order->method('getStoreId')->willReturn(1);
            $order->method('formatPriceTxt')->willReturnCallback(static fn($v) => sprintf('$%.2f', $v));
            $total->setOrder($order);
        }
        if ($source) {
            $total->setSource($source);
        }
        if ($prefix !== null) {
            $total->setAmountPrefix($prefix);
        }

        return $total;
    }

    private function row(string $label, float $fee, float $tax = 0.0): array
    {
        return ['label' => $label, 'fee' => $fee, 'base_fee' => $fee, 'tax' => $tax, 'base_tax' => $tax];
    }

    public function testEachFeeIsPrintedOnItsOwnLine(): void
    {
        $this->rows = [$this->row('Handling', 1.5), $this->row('Packing', 1.0)];

        $lines = $this->total(new DataObject())->getTotalsForDisplay();

        $this->assertSame([
            ['amount' => '$1.50', 'label' => 'Handling:', 'font_size' => 7],
            ['amount' => '$1.00', 'label' => 'Packing:', 'font_size' => 7],
        ], $lines);
    }

    public function testZeroFeesAreSkippedUnlessConfigured(): void
    {
        $this->rows = [$this->row('Handling', 1.5), $this->row('Free', 0.0)];

        $this->assertCount(1, $this->total(new DataObject())->getTotalsForDisplay());

        $this->config['isShowZeroFees'] = true;
        $this->assertCount(2, $this->total(new DataObject())->getTotalsForDisplay());
    }

    public function testFeesAreSummedUnderTheTitleWithoutBreakdown(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $this->rows = [$this->row('Handling', 1.5, 0.12), $this->row('Packing', 1.0)];

        $lines = $this->total(new DataObject())->getTotalsForDisplay();

        $this->assertSame([['amount' => '$2.50', 'label' => 'Additional Fees:', 'font_size' => 7]], $lines);
    }

    public function testIncludingTaxDisplayAddsTheFeeTax(): void
    {
        $this->config['getTaxDisplay'] = 2;
        $this->rows = [$this->row('Handling', 1.5, 0.12)];

        $lines = $this->total(new DataObject())->getTotalsForDisplay();

        $this->assertSame('$1.62', $lines[0]['amount']);
    }

    public function testCreditMemoPrefixAndFontSizeAreUsed(): void
    {
        $this->rows = [$this->row('', 2.0)];
        $total = $this->total(new DataObject(), true, '-');
        $total->setFontSize(9);

        $this->assertSame(
            [['amount' => '-$2.00', 'label' => 'Additional Fees:', 'font_size' => 9]],
            $total->getTotalsForDisplay()
        );
    }

    public function testStoredAmountIsUsedWhenNoFeeLinesWereSaved(): void
    {
        $this->rows = null;

        $lines = $this->total(new DataObject(['panth_extra_fee_amount' => '3.5']))->getTotalsForDisplay();
        $this->assertSame([['amount' => '$3.50', 'label' => 'Additional Fees:', 'font_size' => 7]], $lines);

        $this->assertSame([], $this->total(new DataObject())->getTotalsForDisplay());
    }

    public function testCanDisplayFollowsTheLines(): void
    {
        $this->rows = [$this->row('Handling', 1.5)];
        $this->assertTrue($this->total(new DataObject())->canDisplay());

        $this->rows = [];
        $this->assertFalse($this->total(new DataObject())->canDisplay());
    }

    public function testNothingIsPrintedWithoutSourceOrOrder(): void
    {
        $this->rows = [$this->row('Handling', 1.5)];

        $this->assertSame([], $this->total(null)->getTotalsForDisplay());
        $this->assertSame([], $this->total(new DataObject(), false)->getTotalsForDisplay());
    }

    public function testSmallOrderFeeTypeOffersOnlySupportedTypes(): void
    {
        $values = array_column((new SmallOrderFeeType())->toOptionArray(), 'value');

        $this->assertSame(['fixed', 'percent'], $values);
    }
}
