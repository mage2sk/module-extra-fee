<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\OrderFeeFactory;
use Panth\ExtraFee\Model\QuoteFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\Collection as QuoteFeeCollection;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;
use Panth\ExtraFee\Observer\MarkMultishippingOrder;
use Panth\ExtraFee\Observer\SaveOrderFees;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SaveOrderFeesTest extends TestCase
{
    use ObjectHelperTrait;

    private array $saved = [];

    private int $existing = 0;

    private array $quoteFees = [];

    private array $filters = [];

    private function observerFor(?LoggerInterface $logger = null, ?\Exception $saveError = null): SaveOrderFees
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnCallback(fn() => (string)$this->existing);

        $orderFeeResource = $this->createStub(OrderFeeResource::class);
        $orderFeeResource->method('getConnection')->willReturn($connection);
        $orderFeeResource->method('getMainTable')->willReturn('panth_extra_fee_order');
        $orderFeeResource->method('save')->willReturnCallback(function ($fee) use ($orderFeeResource, $saveError) {
            if ($saveError) {
                throw $saveError;
            }
            $this->saved[] = $fee->getData();
            return $orderFeeResource;
        });

        $factory = $this->createStub(OrderFeeFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newWithoutConstructor(OrderFee::class));

        $collection = $this->collectionOf(QuoteFeeCollection::class, $this->quoteFees);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $value) use ($collection) {
            $this->filters[] = [$field, $value];
            return $collection;
        });

        return new SaveOrderFees(
            $factory,
            $orderFeeResource,
            $this->factoryReturning(QuoteFeeCollectionFactory::class, $collection),
            $this->createStub(QuoteFeeResource::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            new Json()
        );
    }

    private function order(array $data, array $items = []): Order
    {
        $order = new class extends Order {
            public array $itemsDouble = [];

            public function __construct()
            {
                $this->_data = [];
            }

            public function getAllItems()
            {
                return $this->itemsDouble;
            }
        };
        $order->setData($data);
        $order->itemsDouble = $items;
        return $order;
    }

    private function quoteFee(array $data): QuoteFee
    {
        return $this->newWithoutConstructor(QuoteFee::class, $data + [
            'rule_id' => 1,
            'fee_label' => 'Handling',
            'fee_type' => 'fixed',
            'base_fee_amount' => 5,
            'fee_amount' => 10,
            'base_tax_amount' => 1,
            'tax_amount' => 2,
            'tax_charged' => 1,
        ]);
    }

    private function event(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }

    public function testQuoteFeesAreCopiedToTheOrder(): void
    {
        $this->quoteFees = [$this->quoteFee(['item_breakdown' => '{"100":2.5,"101":2.5,"999":1}'])];
        $items = [
            new DataObject(['id' => 500, 'quote_item_id' => 100]),
            new DataObject(['id' => 501, 'quote_item_id' => 101]),
            new DataObject(['id' => 502, 'quote_item_id' => null]),
        ];
        $order = $this->order(['id' => 7, 'quote_id' => 3, 'increment_id' => '000000007'], $items);

        $this->observerFor()->execute($this->event(['order' => $order]));

        $this->assertSame([['quote_id', 3]], $this->filters);
        $this->assertCount(1, $this->saved);
        $row = $this->saved[0];
        $this->assertSame(7, $row['order_id']);
        $this->assertSame(3, $row['quote_id']);
        $this->assertSame(6.0, $row['base_fee_amount_incl_tax']);
        $this->assertSame(12.0, $row['fee_amount_incl_tax']);
        $this->assertSame(1, $row['tax_charged']);
        $this->assertSame(['500' => 2.5, '501' => 2.5], json_decode($row['item_breakdown'], true));
    }

    public function testBreakdownIsNullWhenEmptyInvalidOrUnmapped(): void
    {
        $this->quoteFees = [
            $this->quoteFee(['item_breakdown' => '']),
            $this->quoteFee(['item_breakdown' => 'not json']),
            $this->quoteFee(['item_breakdown' => '"scalar"']),
            $this->quoteFee(['item_breakdown' => '{"42":1}']),
        ];
        $order = $this->order(['id' => 7, 'quote_id' => 3]);

        $this->observerFor()->execute($this->event(['order' => $order]));

        $this->assertSame([null, null, null, null], array_column($this->saved, 'item_breakdown'));
    }

    public function testOrdersThatAlreadyHaveFeesAreNotDuplicated(): void
    {
        $this->existing = 2;
        $this->quoteFees = [$this->quoteFee([])];

        $this->observerFor()->execute($this->event(['order' => $this->order(['id' => 7, 'quote_id' => 3])]));

        $this->assertSame([], $this->saved);
    }

    public function testSkippedMultishippingOrdersGetNoFees(): void
    {
        $this->quoteFees = [$this->quoteFee([])];
        $order = $this->order(['id' => 7, 'quote_id' => 3, MarkMultishippingOrder::SKIP_FLAG => true]);

        $this->observerFor()->execute($this->event(['order' => $order]));

        $this->assertSame([], $this->saved);
    }

    public function testOrderWithoutQuoteIsIgnored(): void
    {
        $this->quoteFees = [$this->quoteFee([])];

        $this->observerFor()->execute($this->event(['order' => $this->order(['id' => 7])]));

        $this->assertSame([], $this->saved);
    }

    public function testNoQuoteFeesMeansNothingToSave(): void
    {
        $this->observerFor()->execute($this->event(['order' => $this->order(['id' => 7, 'quote_id' => 3])]));

        $this->assertSame([], $this->saved);
    }

    public function testMultishippingOrdersListIsProcessed(): void
    {
        $this->quoteFees = [$this->quoteFee([])];
        $orders = [
            $this->order(['id' => 7, 'quote_id' => 3]),
            $this->order(['quote_id' => 3]),
            new DataObject(['id' => 9, 'quote_id' => 3]),
        ];

        $this->observerFor()->execute($this->event(['order' => $this->order([]), 'orders' => $orders]));

        $this->assertSame([7], array_column($this->saved, 'order_id'));
    }

    public function testErrorsAreLoggedNotThrown(): void
    {
        $this->quoteFees = [$this->quoteFee([])];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('disk full'));

        $this->observerFor($logger, new \RuntimeException('disk full'))
            ->execute($this->event(['order' => $this->order(['id' => 7, 'quote_id' => 3])]));
    }
}
