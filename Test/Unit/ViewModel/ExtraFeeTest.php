<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection as OrderFeeCollection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\Collection as QuoteFeeCollection;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\ViewModel\ExtraFee;
use PHPUnit\Framework\TestCase;

class ExtraFeeTest extends TestCase
{
    use ObjectHelperTrait;

    private array $filters = [];

    private function viewModel(): ExtraFee
    {
        $orderCollection = $this->collectionOf(OrderFeeCollection::class, [new DataObject(['fee_label' => 'A'])]);
        $orderCollection->method('addFieldToFilter')->willReturnCallback(function ($f, $v) use ($orderCollection) {
            $this->filters[] = [$f, $v];
            return $orderCollection;
        });
        $quoteCollection = $this->collectionOf(QuoteFeeCollection::class, [
            new DataObject(['fee_label' => 'B']),
            new DataObject(['fee_label' => 'C']),
        ]);
        $quoteCollection->method('addFieldToFilter')->willReturnCallback(function ($f, $v) use ($quoteCollection) {
            $this->filters[] = [$f, $v];
            return $quoteCollection;
        });

        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturn(true);
        $helper->method('getTaxDisplay')->willReturn(3);
        $helper->method('isShowFeeBreakdown')->willReturn(true);
        $helper->method('getFeeDisplayTitle')->willReturn('Fees');

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn($amount, $container, $precision) => '$' . number_format($amount, $precision)
        );

        return new ExtraFee(
            $helper,
            $this->factoryReturning(OrderFeeCollectionFactory::class, $orderCollection),
            $this->factoryReturning(QuoteFeeCollectionFactory::class, $quoteCollection),
            $priceCurrency
        );
    }

    public function testOrderFeesAreReturnedAsArrays(): void
    {
        $this->assertSame([['fee_label' => 'A']], $this->viewModel()->getOrderFees(7));
        $this->assertSame([['order_id', 7]], $this->filters);
    }

    public function testQuoteFeesAreReturnedAsArrays(): void
    {
        $this->assertSame([['fee_label' => 'B'], ['fee_label' => 'C']], $this->viewModel()->getQuoteFees(3));
        $this->assertSame([['quote_id', 3]], $this->filters);
    }

    public function testDisplaySettingsComeFromTheHelper(): void
    {
        $viewModel = $this->viewModel();

        $this->assertTrue($viewModel->isEnabled());
        $this->assertSame(3, $viewModel->getTaxDisplay());
        $this->assertTrue($viewModel->isShowFeeBreakdown());
        $this->assertSame('Fees', $viewModel->getFeeDisplayTitle());
        $this->assertSame('$4.50', $viewModel->formatPrice(4.5));
    }
}
