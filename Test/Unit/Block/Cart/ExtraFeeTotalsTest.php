<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\DataObject;
use Panth\ExtraFee\Block\Cart\ExtraFeeTotals;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\Test\Unit\Fixture\QuoteDouble;
use PHPUnit\Framework\TestCase;

class ExtraFeeTotalsTest extends TestCase
{
    use ObjectHelperTrait;

    private array $config = [
        'isEnabled' => true,
        'isShowInCart' => true,
        'isShowZeroFees' => false,
        'getFeeDisplayTitle' => 'Fees',
        'getTaxDisplay' => 2,
        'isShowFeeBreakdown' => true,
        'isSmallOrderFeeEnabled' => true,
        'getSmallOrderMinAmount' => 50.0,
        'getSmallOrderFeeAmount' => 5.0,
        'getSmallOrderMessage' => 'Add %1 to avoid a %2 fee',
    ];

    private int $created = 0;

    private function block(array $fees, ?QuoteDouble $quote): ExtraFeeTotals
    {
        $helper = $this->createStub(Helper::class);
        foreach (array_keys($this->config) as $method) {
            $helper->method($method)->willReturnCallback(fn() => $this->config[$method]);
        }
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($fees) {
            $this->created++;
            $collection = $this->collectionOf(Collection::class, array_map(
                static fn($row) => new DataObject($row),
                $fees
            ));
            $collection->method('addFieldToFilter')->willReturnSelf();
            return $collection;
        });
        $session = $this->createStub(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        return $this->blockOf(ExtraFeeTotals::class, [
            'helper' => $helper,
            'quoteFeeCollectionFactory' => $factory,
            'checkoutSession' => $session,
        ]);
    }

    private function blockOf(string $class, array $props): ExtraFeeTotals
    {
        $block = $this->newWithoutConstructor($class);
        foreach ($props as $name => $value) {
            $this->inject($block, $class, $name, $value);
        }
        return $block;
    }

    private function quote(array $data = ['id' => 3, 'subtotal' => 20], bool $withStore = true): QuoteDouble
    {
        $quote = new QuoteDouble($data);
        if ($withStore) {
            $currency = $this->createStub(Currency::class);
            $currency->method('format')->willReturnCallback(static fn($v) => '$' . number_format((float)$v, 2));
            $quote->storeDouble = new DataObject(['current_currency' => $currency]);
        }
        return $quote;
    }

    public function testFeesAreMappedAndZeroFeesHidden(): void
    {
        $block = $this->block([
            ['fee_label' => 'Packing', 'fee_type' => 'fixed', 'base_fee_amount' => 1, 'fee_amount' => 2,
                'base_tax_amount' => 0.1, 'tax_amount' => 0.2],
            ['fee_label' => 'Zero', 'fee_amount' => 0],
        ], $this->quote());

        $fees = $block->getExtraFees();

        $this->assertCount(1, $fees);
        $this->assertSame('Packing', $fees[0]['label']);
        $this->assertSame(2.0, $fees[0]['fee_amount']);
        $this->assertEqualsWithDelta(2.2, $fees[0]['fee_amount_incl_tax'], 0.00001);
    }

    public function testFeesAreCachedPerBlock(): void
    {
        $block = $this->block([['fee_label' => 'A', 'fee_amount' => 1]], $this->quote());

        $block->getExtraFees();
        $block->getExtraFees();

        $this->assertSame(1, $this->created);
    }

    public function testZeroFeesAreListedWhenConfigured(): void
    {
        $this->config['isShowZeroFees'] = true;

        $this->assertCount(1, $this->block([['fee_label' => 'Zero', 'fee_amount' => 0]], $this->quote())->getExtraFees());
    }

    public function testNoFeesWithoutASavedQuote(): void
    {
        $this->assertSame([], $this->block([['fee_amount' => 1]], $this->quote(['subtotal' => 1]))->getExtraFees());
        $this->assertSame([], $this->block([['fee_amount' => 1]], null)->getExtraFees());
    }

    public function testCanShowNeedsModuleAndCartFlag(): void
    {
        $this->assertTrue($this->block([], $this->quote())->canShow());

        $this->config['isShowInCart'] = false;
        $this->assertFalse($this->block([], $this->quote())->canShow());
    }

    public function testDisplaySettingsPassThrough(): void
    {
        $block = $this->block([], $this->quote());

        $this->assertSame('Fees', $block->getFeeDisplayTitle());
        $this->assertSame(2, $block->getTaxDisplay());
        $this->assertTrue($block->isShowFeeBreakdown());
    }

    public function testSmallOrderFeeIsActiveBelowTheMinimum(): void
    {
        $this->assertTrue($this->block([], $this->quote(['id' => 3, 'subtotal' => 20]))->isSmallOrderFeeActive());
        $this->assertFalse($this->block([], $this->quote(['id' => 3, 'subtotal' => 50]))->isSmallOrderFeeActive());
        $this->assertFalse($this->block([], $this->quote(['subtotal' => 20]))->isSmallOrderFeeActive());

        $this->config['isSmallOrderFeeEnabled'] = false;
        $this->assertFalse($this->block([], $this->quote())->isSmallOrderFeeActive());
    }

    public function testSmallOrderMessageFillsAmountsInStoreCurrency(): void
    {
        $this->assertSame(
            'Add $50.00 to avoid a $5.00 fee',
            $this->block([], $this->quote(['id' => 3, 'subtotal' => 20, 'quote_currency_code' => 'USD']))
                ->getSmallOrderMessage()
        );
    }

    public function testSmallOrderMessageWorksWithoutQuoteCurrency(): void
    {
        $this->assertSame(
            'Add $50.00 to avoid a $5.00 fee',
            $this->block([], $this->quote(['id' => 3, 'subtotal' => 20]))->getSmallOrderMessage()
        );
    }

    public function testPriceFallsBackToPlainNumberWithoutStore(): void
    {
        $this->assertSame('1,234.50', $this->block([], $this->quote(['id' => 3], false))->formatPrice(1234.5));
        $this->assertSame('3.00', $this->block([], null)->formatPrice(3));
    }
}
