<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Total\Invoice;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Invoice;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Model\Total\Invoice\ExtraFee;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExtraFeeTest extends TestCase
{
    use ObjectHelperTrait;

    private function invoice(int $orderId, bool $isLast = false, array $data = []): Invoice
    {
        $invoice = new class extends Invoice {
            public $orderDouble;

            public bool $lastDouble = false;

            public function __construct()
            {
                $this->_data = [];
            }

            public function getOrder()
            {
                return $this->orderDouble;
            }

            public function isLast()
            {
                return $this->lastDouble;
            }
        };
        $invoice->orderDouble = new DataObject(['id' => $orderId]);
        $invoice->lastDouble = $isLast;
        $invoice->setData($data + [
            'grand_total' => 100.0,
            'base_grand_total' => 50.0,
            'tax_amount' => 10.0,
            'base_tax_amount' => 5.0,
        ]);
        return $invoice;
    }

    private function orderFee(array $data): OrderFee
    {
        $fee = $this->newWithoutConstructor(OrderFee::class, $data + [
            'fee_label' => 'Handling',
            'base_fee_invoiced' => 0,
            'fee_invoiced' => 0,
            'base_tax_amount' => 0,
            'tax_amount' => 0,
        ]);
        $fee->setIdFieldName('entity_id');
        return $fee;
    }

    private function collector(array $fees, ?LoggerInterface $logger = null, bool $throws = false): ExtraFee
    {
        $collection = $this->collectionOf(Collection::class, $fees);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $factory = $this->createStub(CollectionFactory::class);
        if ($throws) {
            $factory->method('create')->willThrowException(new \RuntimeException('db gone'));
        } else {
            $factory->method('create')->willReturn($collection);
        }

        return new ExtraFee($factory, $logger ?? $this->createStub(LoggerInterface::class), new Json());
    }

    public function testInvoiceWithoutOrderIdIsLeftAlone(): void
    {
        $invoice = $this->invoice(0);

        $this->collector([$this->orderFee(['entity_id' => 1, 'base_fee_amount' => 5, 'fee_amount' => 5])])
            ->collect($invoice);

        $this->assertSame([], $invoice->getData(ExtraFee::ALLOCATIONS_KEY));
        $this->assertSame(100.0, $invoice->getGrandTotal());
    }

    public function testRemainingFeeIsAddedToGrandTotalAndRecorded(): void
    {
        $invoice = $this->invoice(3);
        $fee = $this->orderFee([
            'entity_id' => 11,
            'base_fee_amount' => 10,
            'fee_amount' => 20,
            'base_fee_invoiced' => 4,
            'fee_invoiced' => 8,
            'base_tax_amount' => 2,
            'tax_amount' => 4,
        ]);

        $this->collector([$fee])->collect($invoice);

        $this->assertSame(112.0, $invoice->getGrandTotal());
        $this->assertSame(56.0, $invoice->getBaseGrandTotal());
        $this->assertSame(12.0, $invoice->getData('panth_extra_fee_amount'));
        $this->assertSame(6.0, $invoice->getData('panth_base_extra_fee_amount'));
        $this->assertSame(2.4, $invoice->getData('panth_extra_fee_tax'));
        $this->assertSame(1.2, $invoice->getData('panth_base_extra_fee_tax'));
        $this->assertSame(
            [11 => ['base_fee' => 6.0, 'fee' => 12.0, 'base_tax' => 1.2, 'tax' => 2.4]],
            $invoice->getData(ExtraFee::ALLOCATIONS_KEY)
        );
        $this->assertEquals(
            [['label' => 'Handling', 'fee' => 12.0, 'base_fee' => 6.0, 'tax' => 2.4, 'base_tax' => 1.2]],
            json_decode($invoice->getData(DocumentFeeTotals::DETAILS_KEY), true)
        );
    }

    public function testFullyInvoicedFeesAreSkipped(): void
    {
        $invoice = $this->invoice(3);
        $fee = $this->orderFee(['entity_id' => 11, 'base_fee_amount' => 10, 'fee_amount' => 10, 'base_fee_invoiced' => 10]);

        $this->collector([$fee])->collect($invoice);

        $this->assertSame([], $invoice->getData(ExtraFee::ALLOCATIONS_KEY));
        $this->assertSame('[]', $invoice->getData(DocumentFeeTotals::DETAILS_KEY));
        $this->assertSame(100.0, $invoice->getGrandTotal());
        $this->assertNull($invoice->getData('panth_extra_fee_amount'));
    }

    public function testChargedFeeTaxIsAddedToInvoiceTaxWhenNotLast(): void
    {
        $invoice = $this->invoice(3, false);
        $fee = $this->orderFee([
            'entity_id' => 11,
            'base_fee_amount' => 10,
            'fee_amount' => 10,
            'base_tax_amount' => 2,
            'tax_amount' => 2,
            'tax_charged' => 1,
        ]);

        $this->collector([$fee])->collect($invoice);

        $this->assertSame(12.0, $invoice->getTaxAmount());
        $this->assertSame(7.0, $invoice->getBaseTaxAmount());
        $this->assertSame(112.0, $invoice->getGrandTotal());
        $this->assertSame(62.0, $invoice->getBaseGrandTotal());
    }

    public function testChargedFeeTaxIsNotAddedAgainOnTheLastInvoice(): void
    {
        $invoice = $this->invoice(3, true);
        $fee = $this->orderFee([
            'entity_id' => 11,
            'base_fee_amount' => 10,
            'fee_amount' => 10,
            'base_tax_amount' => 2,
            'tax_amount' => 2,
            'tax_charged' => 1,
        ]);

        $this->collector([$fee])->collect($invoice);

        $this->assertSame(10.0, $invoice->getTaxAmount());
        $this->assertSame(110.0, $invoice->getGrandTotal());
    }

    public function testUnchargedTaxIsReportedButNotAddedToGrandTotal(): void
    {
        $invoice = $this->invoice(3, false);
        $fee = $this->orderFee([
            'entity_id' => 11,
            'base_fee_amount' => 10,
            'fee_amount' => 10,
            'base_tax_amount' => 2,
            'tax_amount' => 2,
            'tax_charged' => 0,
        ]);

        $this->collector([$fee])->collect($invoice);

        $this->assertSame(10.0, $invoice->getTaxAmount());
        $this->assertSame(110.0, $invoice->getGrandTotal());
        $this->assertSame(2.0, $invoice->getData('panth_extra_fee_tax'));
    }

    public function testErrorsAreLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('db gone'));
        $invoice = $this->invoice(3);

        $this->collector([], $logger, true)->collect($invoice);

        $this->assertSame(100.0, $invoice->getGrandTotal());
    }
}
