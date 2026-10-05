<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\ExtraFee\Block\Adminhtml\Sales\Order\Creditmemo\Totals\ExtraFee as CreditmemoTotals;
use Panth\ExtraFee\Block\Adminhtml\Sales\Order\Invoice\Totals\ExtraFee as InvoiceTotals;
use Panth\ExtraFee\Block\Adminhtml\Sales\Order\Totals\ExtraFee as OrderTotals;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\FeeRuleRepository;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\BlockTestTrait;
use Panth\ExtraFee\Test\Unit\Fixture\TotalsParentDouble;
use PHPUnit\Framework\TestCase;

class TotalsBlocksTest extends TestCase
{
    use BlockTestTrait;

    private array $config = [
        'isEnabled' => true,
        'getTaxDisplay' => 1,
        'isShowFeeBreakdown' => true,
        'isShowZeroFees' => false,
        'getFeeDisplayTitle' => 'Extra Fees',
    ];

    private function helper(): Helper
    {
        $helper = $this->createStub(Helper::class);
        foreach (array_keys($this->config) as $method) {
            $helper->method($method)->willReturnCallback(fn() => $this->config[$method]);
        }
        return $helper;
    }

    private function factory(array $fees): CollectionFactory
    {
        $collection = $this->collectionOf(Collection::class, $fees);
        $collection->method('addOrderFilter')->willReturnSelf();
        return $this->factoryReturning(CollectionFactory::class, $collection);
    }

    private function fee(array $data): OrderFee
    {
        return $this->newWithoutConstructor(OrderFee::class, $data + ['fee_label' => 'Handling']);
    }

    private function parent(int $orderId = 7, $source = 'source'): TotalsParentDouble
    {
        return new TotalsParentDouble([
            'order' => new DataObject(['id' => $orderId, 'store_id' => 1]),
            'source' => $source === 'source' ? new DataObject() : $source,
        ]);
    }

    private function documentTotals(?array $rows): DocumentFeeTotals
    {
        $document = $this->createStub(DocumentFeeTotals::class);
        $document->method('getRows')->willReturn($rows);
        $document->method('addTotals')->willReturnCallback(static function ($parent, $rows) {
            foreach ($rows as $i => $row) {
                $parent->addTotal(new DataObject(['code' => 'doc_' . $i, 'value' => $row['fee']]), 'tax');
            }
        });
        return $document;
    }

    private function orderBlock(array $fees, $parent): OrderTotals
    {
        return $this->block(OrderTotals::class, [
            'helper' => $this->helper(),
            'orderFeeCollectionFactory' => $this->factory($fees),
        ], $parent);
    }

    private function invoiceBlock(array $fees, $parent, ?array $documentRows = null): InvoiceTotals
    {
        return $this->block(InvoiceTotals::class, [
            'helper' => $this->helper(),
            'orderFeeCollectionFactory' => $this->factory($fees),
            'documentFeeTotals' => $this->documentTotals($documentRows),
        ], $parent);
    }

    private function creditmemoBlock(array $fees, $parent, array $refundable = [], ?array $documentRows = null): CreditmemoTotals
    {
        $repository = $this->createStub(FeeRuleRepository::class);
        $repository->method('getById')->willReturnCallback(function ($id) use ($refundable) {
            if (!array_key_exists($id, $refundable)) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->newWithoutConstructor(FeeRule::class, ['is_refundable' => $refundable[$id]]);
        });

        return $this->block(CreditmemoTotals::class, [
            'helper' => $this->helper(),
            'orderFeeCollectionFactory' => $this->factory($fees),
            'feeRuleRepository' => $repository,
            'documentFeeTotals' => $this->documentTotals($documentRows),
        ], $parent);
    }

    public function testOrderBreakdownAddsOneTotalPerFee(): void
    {
        $parent = $this->parent();
        $fees = [
            $this->fee(['fee_label' => 'A', 'fee_amount' => 2, 'base_fee_amount' => 1]),
            $this->fee(['fee_label' => 'Zero', 'fee_amount' => 0]),
            $this->fee(['fee_label' => 'B', 'fee_amount' => 3, 'base_fee_amount' => 1.5]),
        ];

        $this->orderBlock($fees, $parent)->initTotals();

        $this->assertSame(['panth_extra_fee_0', 'panth_extra_fee_1'], $parent->codes());
        $this->assertSame([2.0, 3.0], $parent->values());
        $this->assertSame(['A', 'B'], $parent->labels());
        $this->assertSame('tax', $parent->totals[0]['after']);
    }

    public function testOrderBreakdownIncludingTax(): void
    {
        $this->config['getTaxDisplay'] = 2;
        $parent = $this->parent();

        $this->orderBlock([$this->fee(['fee_amount' => 2, 'tax_amount' => 0.4])], $parent)->initTotals();

        $this->assertSame([2.4], $parent->values());
        $this->assertSame(['Handling (Incl. Tax)'], $parent->labels());
    }

    public function testOrderBreakdownBothTaxModes(): void
    {
        $this->config['getTaxDisplay'] = 3;
        $parent = $this->parent();

        $this->orderBlock([$this->fee(['fee_amount' => 2, 'tax_amount' => 0.4])], $parent)->initTotals();

        $this->assertSame(['panth_extra_fee_0_excl', 'panth_extra_fee_0_incl'], $parent->codes());
        $this->assertSame([2.0, 2.4], $parent->values());
    }

    public function testOrderAggregatedTotalUsesTheDisplayTitle(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $parent = $this->parent();
        $fees = [$this->fee(['fee_amount' => 2]), $this->fee(['fee_amount' => 3])];

        $this->orderBlock($fees, $parent)->initTotals();

        $this->assertSame(['panth_extra_fee'], $parent->codes());
        $this->assertSame([5.0], $parent->values());
        $this->assertSame(['Extra Fees'], $parent->labels());
    }

    public function testOrderAggregatedTaxModes(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $fees = [$this->fee(['fee_amount' => 2, 'tax_amount' => 0.5])];

        $this->config['getTaxDisplay'] = 2;
        $incl = $this->parent();
        $this->orderBlock($fees, $incl)->initTotals();
        $this->assertSame([2.5], $incl->values());

        $this->config['getTaxDisplay'] = 3;
        $both = $this->parent();
        $this->orderBlock($fees, $both)->initTotals();
        $this->assertSame(['panth_extra_fee_excl', 'panth_extra_fee_incl'], $both->codes());
    }

    public function testOrderAggregatedZeroTotalIsHiddenUnlessConfigured(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $hidden = $this->parent();
        $this->orderBlock([$this->fee(['fee_amount' => 0])], $hidden)->initTotals();
        $this->assertSame([], $hidden->totals);

        $this->config['isShowZeroFees'] = true;
        $shown = $this->parent();
        $this->orderBlock([$this->fee(['fee_amount' => 0])], $shown)->initTotals();
        $this->assertSame([0.0], $shown->values());
    }

    public function testOrderBlockDoesNothingWhenDisabledOrWithoutContext(): void
    {
        $noFees = $this->parent();
        $this->orderBlock([], $noFees)->initTotals();
        $noOrder = $this->parent(0);
        $this->orderBlock([$this->fee(['fee_amount' => 2])], $noOrder)->initTotals();
        $this->assertSame([], $noFees->totals);
        $this->assertSame([], $noOrder->totals);

        $block = $this->orderBlock([$this->fee(['fee_amount' => 2])], null);
        $this->assertSame($block, $block->initTotals());

        $this->config['isEnabled'] = false;
        $disabled = $this->parent();
        $this->orderBlock([$this->fee(['fee_amount' => 2])], $disabled)->initTotals();
        $this->assertSame([], $disabled->totals);
    }

    public function testInvoicePrefersDocumentRows(): void
    {
        $parent = $this->parent();

        $this->invoiceBlock([$this->fee(['fee_invoiced' => 9])], $parent, [['fee' => 4.0]])->initTotals();

        $this->assertSame(['doc_0'], $parent->codes());
    }

    public function testLegacyInvoiceUsesInvoicedAmounts(): void
    {
        $parent = $this->parent();
        $fees = [
            $this->fee(['fee_label' => 'A', 'fee_invoiced' => 4, 'base_fee_invoiced' => 2, 'fee_amount' => 99]),
            $this->fee(['fee_label' => 'B', 'fee_invoiced' => 0, 'fee_amount' => 99]),
        ];

        $this->invoiceBlock($fees, $parent)->initTotals();

        $this->assertSame([4.0], $parent->values());
        $this->assertSame(['A'], $parent->labels());
    }

    public function testLegacyInvoiceAggregatedAndTaxModes(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $this->config['getTaxDisplay'] = 3;
        $parent = $this->parent();
        $fees = [
            $this->fee(['fee_invoiced' => 4, 'tax_amount' => 1]),
            $this->fee(['fee_invoiced' => 1, 'tax_amount' => 0]),
        ];

        $this->invoiceBlock($fees, $parent)->initTotals();

        $this->assertSame(['panth_extra_fee_excl', 'panth_extra_fee_incl'], $parent->codes());
        $this->assertSame([5.0, 6.0], $parent->values());
    }

    public function testInvoiceBlockNeedsASource(): void
    {
        $parent = $this->parent(7, null);

        $this->invoiceBlock([$this->fee(['fee_invoiced' => 4])], $parent)->initTotals();

        $this->assertSame([], $parent->totals);
    }

    public function testCreditmemoPrefersDocumentRows(): void
    {
        $parent = $this->parent();

        $this->creditmemoBlock([$this->fee(['fee_invoiced' => 9])], $parent, [], [['fee' => 2.0]])->initTotals();

        $this->assertSame([2.0], $parent->values());
    }

    public function testLegacyCreditmemoShowsRefundableRemainder(): void
    {
        $this->config['getTaxDisplay'] = 2;
        $parent = $this->parent();
        $fees = [
            $this->fee([
                'fee_label' => 'A',
                'rule_id' => 1,
                'fee_invoiced' => 10,
                'fee_refunded' => 4,
                'tax_amount' => 2,
                'tax_refunded' => 1,
            ]),
            $this->fee(['fee_label' => 'NonRefundable', 'rule_id' => 2, 'fee_invoiced' => 10]),
            $this->fee(['fee_label' => 'DeletedRule', 'rule_id' => 3, 'fee_invoiced' => 1]),
            $this->fee(['fee_label' => 'Done', 'rule_id' => 1, 'fee_invoiced' => 5, 'fee_refunded' => 5]),
        ];

        $this->creditmemoBlock($fees, $parent, [1 => 1, 2 => 0])->initTotals();

        $this->assertSame(['A (Incl. Tax)', 'DeletedRule (Incl. Tax)'], $parent->labels());
        $this->assertSame([7.0, 1.0], $parent->values());
    }

    public function testLegacyCreditmemoAggregatedSkipsNonRefundable(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $parent = $this->parent();
        $fees = [
            $this->fee(['rule_id' => 1, 'fee_invoiced' => 10, 'fee_refunded' => 2]),
            $this->fee(['rule_id' => 2, 'fee_invoiced' => 10]),
        ];

        $this->creditmemoBlock($fees, $parent, [1 => 1, 2 => 0])->initTotals();

        $this->assertSame([8.0], $parent->values());
        $this->assertSame(['Extra Fees'], $parent->labels());
    }

    public function testCreditmemoBlockSkipsWhenNothingToShow(): void
    {
        $this->config['isShowFeeBreakdown'] = false;
        $parent = $this->parent();

        $this->creditmemoBlock([$this->fee(['rule_id' => 2, 'fee_invoiced' => 10])], $parent, [2 => 0])->initTotals();
        $this->creditmemoBlock([], $parent)->initTotals();

        $this->assertSame([], $parent->totals);
    }
}
