<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Block\Sales\Order\Totals;

use Magento\Framework\DataObject;
use Panth\ExtraFee\Block\Sales\Order\Totals\ExtraFee;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\BlockTestTrait;
use Panth\ExtraFee\Test\Unit\Fixture\TotalsParentDouble;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtraFeeTest extends TestCase
{
    use BlockTestTrait;

    private array $config = [
        'isEnabled' => true,
        'getTaxDisplay' => 1,
        'isShowFeeBreakdown' => true,
        'isShowZeroFees' => false,
        'getFeeDisplayTitle' => 'Extra Fees',
        'isShowInOrderView' => true,
        'isShowInInvoice' => true,
        'isShowInCreditmemo' => true,
        'isShowInEmail' => true,
    ];

    private function subject(array $fees, $parent, ?string $area = null, ?array $documentRows = null): ExtraFee
    {
        $helper = $this->createStub(Helper::class);
        foreach (array_keys($this->config) as $method) {
            $helper->method($method)->willReturnCallback(fn() => $this->config[$method]);
        }
        $collection = $this->collectionOf(Collection::class, $fees);
        $collection->method('addOrderFilter')->willReturnSelf();

        $document = $this->createStub(DocumentFeeTotals::class);
        $document->method('getRows')->willReturn($documentRows);
        $document->method('addTotals')->willReturnCallback(static function ($parent, $rows) {
            $parent->addTotal(new DataObject(['code' => 'doc', 'value' => count($rows)]), 'tax');
        });

        return $this->block(ExtraFee::class, [
            'helper' => $helper,
            'orderFeeCollectionFactory' => $this->factoryReturning(CollectionFactory::class, $collection),
            'documentFeeTotals' => $document,
        ], $parent, $area === null ? [] : ['display_area' => $area]);
    }

    private function parent(): TotalsParentDouble
    {
        return new TotalsParentDouble([
            'order' => new DataObject(['id' => 7, 'store_id' => 1]),
            'source' => new DataObject(),
        ]);
    }

    private function fee(array $data): OrderFee
    {
        return $this->newWithoutConstructor(OrderFee::class, $data + ['fee_label' => 'Handling']);
    }

    public static function areas(): array
    {
        return [
            ['order_view', 'isShowInOrderView'],
            ['invoice', 'isShowInInvoice'],
            ['creditmemo', 'isShowInCreditmemo'],
            ['email', 'isShowInEmail'],
        ];
    }

    #[DataProvider('areas')]
    public function testEachDisplayAreaHasItsOwnSwitch(string $area, string $flag): void
    {
        $shown = $this->parent();
        $this->subject([$this->fee(['fee_amount' => 2])], $shown, $area)->initTotals();
        $this->assertSame([2.0], $shown->values());

        $this->config[$flag] = false;
        $hidden = $this->parent();
        $this->subject([$this->fee(['fee_amount' => 2])], $hidden, $area)->initTotals();
        $this->assertSame([], $hidden->totals);
    }

    public function testUnknownAreaIsAlwaysVisible(): void
    {
        $this->config['isShowInOrderView'] = false;
        $parent = $this->parent();

        $this->subject([$this->fee(['fee_amount' => 2])], $parent, 'sidebar')->initTotals();

        $this->assertSame([2.0], $parent->values());
    }

    public function testDocumentRowsWinOverOrderFees(): void
    {
        $parent = $this->parent();

        $this->subject([$this->fee(['fee_amount' => 2])], $parent, 'invoice', [['fee' => 1.0], ['fee' => 1.0]])
            ->initTotals();

        $this->assertSame(['doc'], $parent->codes());
        $this->assertSame([2], $parent->values());
    }

    public function testBreakdownBothTaxModes(): void
    {
        $this->config['getTaxDisplay'] = 3;
        $parent = $this->parent();

        $this->subject([$this->fee(['fee_amount' => 2, 'tax_amount' => 0.2])], $parent)->initTotals();

        $this->assertSame(['Handling (Excl. Tax)', 'Handling (Incl. Tax)'], $parent->labels());
        $this->assertSame([2.0, 2.2], $parent->values());
    }

    public function testBreakdownIncludingTaxAndZeroFeesHidden(): void
    {
        $this->config['getTaxDisplay'] = 2;
        $parent = $this->parent();

        $this->subject([
            $this->fee(['fee_amount' => 2, 'tax_amount' => 0.2]),
            $this->fee(['fee_amount' => 0]),
        ], $parent)->initTotals();

        $this->assertSame([2.2], $parent->values());
    }

    public function testAggregatedModesSumAllFees(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $fees = [$this->fee(['fee_amount' => 2, 'tax_amount' => 1]), $this->fee(['fee_amount' => 3])];

        $excl = $this->parent();
        $this->subject($fees, $excl)->initTotals();
        $this->assertSame([5.0], $excl->values());
        $this->assertSame(['Extra Fees'], $excl->labels());

        $this->config['getTaxDisplay'] = 2;
        $incl = $this->parent();
        $this->subject($fees, $incl)->initTotals();
        $this->assertSame([6.0], $incl->values());

        $this->config['getTaxDisplay'] = 3;
        $both = $this->parent();
        $this->subject($fees, $both)->initTotals();
        $this->assertSame([5.0, 6.0], $both->values());
    }

    public function testNoTotalsWithoutFeesOrWhenDisabled(): void
    {
        $empty = $this->parent();
        $this->subject([], $empty)->initTotals();
        $this->assertSame([], $empty->totals);

        $this->config['isShowFeeBreakdown'] = false;
        $zero = $this->parent();
        $this->subject([$this->fee(['fee_amount' => 0])], $zero)->initTotals();
        $this->assertSame([], $zero->totals);

        $this->config['isEnabled'] = false;
        $disabled = $this->parent();
        $this->subject([$this->fee(['fee_amount' => 2])], $disabled)->initTotals();
        $this->assertSame([], $disabled->totals);
    }

    public function testMissingParentSourceOrOrderIsTolerated(): void
    {
        $noSource = new TotalsParentDouble(['order' => new DataObject(['id' => 7])]);
        $noOrder = new TotalsParentDouble(['source' => new DataObject(), 'order' => new DataObject()]);

        $this->subject([$this->fee(['fee_amount' => 2])], $noSource)->initTotals();
        $this->subject([$this->fee(['fee_amount' => 2])], $noOrder)->initTotals();
        $block = $this->subject([$this->fee(['fee_amount' => 2])], null);

        $this->assertSame([], $noSource->totals);
        $this->assertSame([], $noOrder->totals);
        $this->assertSame($block, $block->initTotals());
    }
}
