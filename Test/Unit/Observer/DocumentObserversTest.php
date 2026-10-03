<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\OrderFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\Total\Creditmemo\ExtraFee as CreditmemoTotal;
use Panth\ExtraFee\Model\Total\Invoice\ExtraFee as InvoiceTotal;
use Panth\ExtraFee\Model\Total\Quote\ExtraFee as QuoteTotal;
use Panth\ExtraFee\Observer\MarkMultishippingOrder;
use Panth\ExtraFee\Observer\PersistPendingQuoteFees;
use Panth\ExtraFee\Observer\RegisterCreditmemoFees;
use Panth\ExtraFee\Observer\RegisterInvoiceFees;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\Test\Unit\Fixture\QuoteDouble;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DocumentObserversTest extends TestCase
{
    use ObjectHelperTrait;

    private array $stored = [];

    private array $saved = [];

    private function event(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }

    private function resource(?\Exception $saveError = null): OrderFeeResource
    {
        $resource = $this->createStub(OrderFeeResource::class);
        $resource->method('load')->willReturnCallback(function ($fee, $id) use ($resource) {
            if (isset($this->stored[$id])) {
                $fee->setData($this->stored[$id]);
            }
            return $resource;
        });
        $resource->method('save')->willReturnCallback(function ($fee) use ($resource, $saveError) {
            if ($saveError) {
                throw $saveError;
            }
            $this->saved[] = $fee->getData();
            return $resource;
        });
        return $resource;
    }

    private function factory(): OrderFeeFactory
    {
        $factory = $this->createStub(OrderFeeFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $fee = $this->newWithoutConstructor(OrderFee::class);
            $fee->setIdFieldName('entity_id');
            return $fee;
        });
        return $factory;
    }

    public function testInvoiceAllocationsAreAddedToInvoicedAmounts(): void
    {
        $this->stored = [11 => ['entity_id' => 11, 'base_fee_invoiced' => 1.0, 'fee_invoiced' => 2.0]];
        $invoice = new DataObject([InvoiceTotal::ALLOCATIONS_KEY => [
            11 => ['base_fee' => 4.0, 'fee' => 8.0, 'base_tax' => 0.0, 'tax' => 0.0],
            12 => ['base_fee' => 1.0, 'fee' => 1.0, 'base_tax' => 0.0, 'tax' => 0.0],
        ]]);

        (new RegisterInvoiceFees($this->factory(), $this->resource(), $this->createStub(LoggerInterface::class)))
            ->execute($this->event(['invoice' => $invoice]));

        $this->assertCount(1, $this->saved);
        $this->assertSame(5.0, $this->saved[0]['base_fee_invoiced']);
        $this->assertSame(10.0, $this->saved[0]['fee_invoiced']);
        $this->assertSame([], $invoice->getData(InvoiceTotal::ALLOCATIONS_KEY));
    }

    public function testInvoiceObserverIgnoresMissingInvoiceOrAllocations(): void
    {
        $observer = new RegisterInvoiceFees($this->factory(), $this->resource(), $this->createStub(LoggerInterface::class));
        $invoice = new DataObject([InvoiceTotal::ALLOCATIONS_KEY => []]);

        $observer->execute($this->event([]));
        $observer->execute($this->event(['invoice' => $invoice]));
        $observer->execute($this->event(['invoice' => new DataObject([InvoiceTotal::ALLOCATIONS_KEY => 'x'])]));

        $this->assertSame([], $this->saved);
    }

    public function testInvoiceObserverLogsSaveErrors(): void
    {
        $this->stored = [11 => ['entity_id' => 11]];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('invoiced fee'));
        $invoice = new DataObject([InvoiceTotal::ALLOCATIONS_KEY => [11 => ['base_fee' => 1, 'fee' => 1]]]);

        (new RegisterInvoiceFees($this->factory(), $this->resource(new \RuntimeException('x')), $logger))
            ->execute($this->event(['invoice' => $invoice]));

        $this->assertSame([], $invoice->getData(InvoiceTotal::ALLOCATIONS_KEY));
    }

    public function testCreditmemoAllocationsAreAddedToRefundedAmounts(): void
    {
        $this->stored = [11 => [
            'entity_id' => 11,
            'base_fee_refunded' => 1.0,
            'fee_refunded' => 1.0,
            'base_tax_refunded' => 0.5,
            'tax_refunded' => 0.5,
        ]];
        $creditmemo = new DataObject([CreditmemoTotal::ALLOCATIONS_KEY => [
            11 => ['base_fee' => 2.0, 'fee' => 3.0, 'base_tax' => 0.25, 'tax' => 0.5],
        ]]);

        (new RegisterCreditmemoFees($this->factory(), $this->resource(), $this->createStub(LoggerInterface::class)))
            ->execute($this->event(['creditmemo' => $creditmemo]));

        $this->assertSame(3.0, $this->saved[0]['base_fee_refunded']);
        $this->assertSame(4.0, $this->saved[0]['fee_refunded']);
        $this->assertSame(0.75, $this->saved[0]['base_tax_refunded']);
        $this->assertSame(1.0, $this->saved[0]['tax_refunded']);
        $this->assertSame([], $creditmemo->getData(CreditmemoTotal::ALLOCATIONS_KEY));
    }

    public function testCreditmemoObserverSkipsUnknownFeesAndLogsErrors(): void
    {
        $this->stored = [11 => ['entity_id' => 11]];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('refunded fee'));
        $creditmemo = new DataObject([CreditmemoTotal::ALLOCATIONS_KEY => [
            99 => ['base_fee' => 1, 'fee' => 1, 'base_tax' => 0, 'tax' => 0],
            11 => ['base_fee' => 1, 'fee' => 1, 'base_tax' => 0, 'tax' => 0],
        ]]);

        (new RegisterCreditmemoFees($this->factory(), $this->resource(new \RuntimeException('x')), $logger))
            ->execute($this->event(['creditmemo' => $creditmemo]));
    }

    public function testCreditmemoObserverIgnoresMissingData(): void
    {
        $observer = new RegisterCreditmemoFees($this->factory(), $this->resource(), $this->createStub(LoggerInterface::class));

        $observer->execute($this->event([]));
        $observer->execute($this->event(['creditmemo' => new DataObject()]));

        $this->assertSame([], $this->saved);
    }

    public function testMultishippingOrderIsSkippedUnlessItsAddressCarriesTheFee(): void
    {
        $observer = new MarkMultishippingOrder();
        $carrierOrder = new DataObject();
        $otherOrder = new DataObject();
        $unflaggedOrder = new DataObject();

        $observer->execute($this->event([
            'order' => $carrierOrder,
            'address' => new DataObject([QuoteTotal::CARRIER_FLAG => true]),
        ]));
        $observer->execute($this->event([
            'order' => $otherOrder,
            'address' => new DataObject([QuoteTotal::CARRIER_FLAG => false]),
        ]));
        $observer->execute($this->event(['order' => $unflaggedOrder, 'address' => new DataObject()]));

        $this->assertNull($carrierOrder->getData(MarkMultishippingOrder::SKIP_FLAG));
        $this->assertTrue($otherOrder->getData(MarkMultishippingOrder::SKIP_FLAG));
        $this->assertTrue($unflaggedOrder->getData(MarkMultishippingOrder::SKIP_FLAG));
    }

    public function testMultishippingObserverNeedsOrderAndAddress(): void
    {
        $order = new DataObject();

        (new MarkMultishippingOrder())->execute($this->event(['order' => $order]));

        $this->assertNull($order->getData(MarkMultishippingOrder::SKIP_FLAG));
    }

    public function testPendingFeesArePersistedOnlyWhenPresent(): void
    {
        $total = $this->createMock(QuoteTotal::class);
        $withPending = new QuoteDouble([QuoteTotal::PENDING_FEES_KEY => ['fees' => []]]);
        $total->expects($this->once())->method('persistPendingFees')->with($withPending);
        $observer = new PersistPendingQuoteFees($total);

        $observer->execute($this->event(['quote' => $withPending]));
        $observer->execute($this->event(['quote' => new QuoteDouble([])]));
        $observer->execute($this->event(['quote' => new DataObject([QuoteTotal::PENDING_FEES_KEY => []])]));
        $observer->execute($this->event([]));
    }
}
