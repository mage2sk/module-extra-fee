<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use PHPUnit\Framework\TestCase;

class DocumentFeeTotalsTest extends TestCase
{
    private function subject(bool $breakdown, int $taxDisplay = 1): DocumentFeeTotals
    {
        $helper = $this->createStub(Helper::class);
        $helper->method('isShowFeeBreakdown')->willReturn($breakdown);
        $helper->method('getTaxDisplay')->willReturn($taxDisplay);
        $helper->method('isShowZeroFees')->willReturn(false);
        $helper->method('getFeeDisplayTitle')->willReturn('Additional Fees');

        return new DocumentFeeTotals($helper, new Json());
    }

    private function invoice($details): Invoice
    {
        $invoice = (new \ReflectionClass(Invoice::class))->newInstanceWithoutConstructor();
        $invoice->setData(DocumentFeeTotals::DETAILS_KEY, $details);
        return $invoice;
    }

    public function testOrderSourceHasNoDocumentRows(): void
    {
        $order = (new \ReflectionClass(Order::class))->newInstanceWithoutConstructor();
        $this->assertNull($this->subject(true)->getRows($order));
    }

    public function testLegacyInvoiceWithoutDetailsReturnsNull(): void
    {
        $this->assertNull($this->subject(true)->getRows($this->invoice(null)));
    }

    public function testInvoiceRowsComeFromTheDocument(): void
    {
        $json = json_encode([['label' => 'Handling', 'fee' => 2.5, 'base_fee' => 2.5, 'tax' => 0.5, 'base_tax' => 0.5]]);
        $rows = $this->subject(true)->getRows($this->invoice($json));
        $this->assertSame([['label' => 'Handling', 'fee' => 2.5, 'base_fee' => 2.5, 'tax' => 0.5, 'base_tax' => 0.5]], $rows);
    }

    public function testEmptyDetailsMeansNoFeeOnThisDocument(): void
    {
        $this->assertSame([], $this->subject(true)->getRows($this->invoice('[]')));
    }

    public function testAggregatedTotalUsesDocumentAmounts(): void
    {
        $parent = new class {
            public array $totals = [];
            public function addTotal(DataObject $total, $after): void
            {
                $this->totals[] = $total;
            }
        };
        $rows = [
            ['label' => 'A', 'fee' => 1.0, 'base_fee' => 1.0, 'tax' => 0.2, 'base_tax' => 0.2],
            ['label' => 'B', 'fee' => 2.0, 'base_fee' => 2.0, 'tax' => 0.4, 'base_tax' => 0.4],
        ];
        $this->subject(false, 2)->addTotals($parent, $rows);
        $this->assertCount(1, $parent->totals);
        $this->assertEqualsWithDelta(3.6, $parent->totals[0]->getData('value'), 0.0001);
    }
}
