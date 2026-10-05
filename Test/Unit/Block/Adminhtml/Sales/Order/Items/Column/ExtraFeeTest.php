<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Block\Adminhtml\Sales\Order\Items\Column;

use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Item;
use Panth\ExtraFee\Block\Adminhtml\Sales\Order\Items\Column\ExtraFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class ExtraFeeTest extends TestCase
{
    use BlockTestTrait;

    private int $collectionsCreated = 0;

    private function column(array $breakdowns, $item, ?DataObject $order = null): ExtraFee
    {
        $fees = array_map(static fn($b) => new DataObject(['item_breakdown' => $b]), $breakdowns);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($fees) {
            $this->collectionsCreated++;
            $collection = $this->collectionOf(Collection::class, $fees);
            $collection->method('addFieldToFilter')->willReturnSelf();
            return $collection;
        });
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn($amount, $container, $precision, $scope, $currency) => $currency . ' ' . number_format($amount, 2)
        );

        $data = ['item' => $item];
        if ($order !== null) {
            $data['order'] = $order;
        }

        return $this->block(ExtraFee::class, [
            'orderFeeCollectionFactory' => $factory,
            'json' => new Json(),
            'priceCurrency' => $priceCurrency,
        ], null, $data);
    }

    private function item(int $id, int $orderId = 7): Item
    {
        return $this->newWithoutConstructor(Item::class, ['id' => $id, 'order_id' => $orderId]);
    }

    public function testItemFeeSumsAllBreakdownsAndConvertsToOrderCurrency(): void
    {
        $column = $this->column(
            ['{"500":1.5,"501":3}', '{"500":0.5}', '', 'broken', '"scalar"'],
            $this->item(500),
            new DataObject(['base_to_order_rate' => 2, 'order_currency_code' => 'EUR'])
        );

        $this->assertSame(4.0, $column->getItemExtraFee());
        $this->assertSame('EUR 4.00', $column->getFormattedItemExtraFee());
    }

    public function testBaseAmountIsUsedWhenRateIsMissing(): void
    {
        $column = $this->column(['{"500":1.5}'], $this->item(500), new DataObject(['order_currency_code' => 'USD']));

        $this->assertSame(1.5, $column->getItemExtraFee());
    }

    public function testItemsWithoutAShareShowNothing(): void
    {
        $column = $this->column(['{"501":3}'], $this->item(500), new DataObject(['base_to_order_rate' => 1]));

        $this->assertSame(0.0, $column->getItemExtraFee());
        $this->assertSame('', $column->getFormattedItemExtraFee());
    }

    public function testUnsavedItemsOrMissingOrderIdShowNothing(): void
    {
        $this->assertSame(0.0, $this->column(['{"0":3}'], $this->item(0))->getItemExtraFee());
        $this->assertSame(0.0, $this->column(['{"500":3}'], $this->item(500, 0))->getItemExtraFee());
        $this->assertSame(0, $this->collectionsCreated);
    }

    public function testNonOrderItemWithoutOrderItemShowsNothing(): void
    {
        $this->assertSame(0.0, $this->column(['{"500":3}'], new DataObject())->getItemExtraFee());
    }

    public function testBreakdownIsLoadedOncePerOrder(): void
    {
        $column = $this->column(['{"500":1}'], $this->item(500), new DataObject(['base_to_order_rate' => 1]));

        $column->getItemExtraFee();
        $column->getItemExtraFee();

        $this->assertSame(1, $this->collectionsCreated);
    }
}
