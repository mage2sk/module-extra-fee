<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Total\Quote;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\Calculator\FeeCalculator;
use Panth\ExtraFee\Model\QuoteFee;
use Panth\ExtraFee\Model\QuoteFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\Collection as QuoteFeeCollection;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;
use Panth\ExtraFee\Model\Total\Quote\ExtraFee;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use Panth\ExtraFee\Test\Unit\Fixture\QuoteDouble;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExtraFeeTest extends TestCase
{
    use ObjectHelperTrait;

    private array $saved = [];

    private array $deletes = [];

    private array $fees = [];

    private bool $apply = true;

    private bool $chargeTax = false;

    private array $storedFees = [];

    private ?\Exception $calculatorException = null;

    private function total(?LoggerInterface $logger = null): ExtraFee
    {
        $calculator = $this->createStub(FeeCalculator::class);
        $calculator->method('calculateFees')->willReturnCallback(function () {
            if ($this->calculatorException) {
                throw $this->calculatorException;
            }
            return $this->fees;
        });

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturnCallback(function ($table, $where) {
            $this->deletes[] = [$table, $where];
            return 1;
        });
        $resource = $this->createStub(QuoteFeeResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('panth_extra_fee_quote');
        $resource->method('save')->willReturnCallback(function ($fee) use ($resource) {
            $this->saved[] = $fee->getData();
            return $resource;
        });

        $factory = $this->createStub(QuoteFeeFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newWithoutConstructor(QuoteFee::class));

        $collection = $this->collectionOf(QuoteFeeCollection::class, $this->storedFees);
        $collection->method('addQuoteFilter')->willReturnSelf();
        $collectionFactory = $this->factoryReturning(QuoteFeeCollectionFactory::class, $collection);

        $helper = $this->createStub(Helper::class);
        $helper->method('shouldApplyFees')->willReturnCallback(fn() => $this->apply);
        $helper->method('isChargeFeeTax')->willReturnCallback(fn() => $this->chargeTax);

        return new ExtraFee(
            $calculator,
            $resource,
            $factory,
            $collectionFactory,
            $helper,
            $logger ?? $this->createStub(LoggerInterface::class),
            new Json()
        );
    }

    private function address(int $id = 1, int $itemCount = 1, string $type = 'shipping'): Address
    {
        $address = new class extends Address {
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
        $address->setData(['id' => $id, 'address_type' => $type]);
        $address->itemsDouble = array_fill(0, $itemCount, new \stdClass());
        return $address;
    }

    private function assignment(?Address $address, array $items = ['item']): ShippingAssignmentInterface
    {
        $shipping = $this->createStub(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);
        $assignment = $this->createStub(ShippingAssignmentInterface::class);
        $assignment->method('getItems')->willReturn($items);
        $assignment->method('getShipping')->willReturn($shipping);
        return $assignment;
    }

    private function totals(): Total
    {
        return $this->newWithoutConstructor(Total::class);
    }

    private function fee(int $ruleId, float $amount, float $tax = 0.0, array $items = []): array
    {
        return [
            'rule_id' => $ruleId,
            'label' => 'Fee ' . $ruleId,
            'fee_type' => 'fixed',
            'base_amount' => $amount,
            'amount' => $amount * 2,
            'base_tax' => $tax,
            'tax' => $tax * 2,
            'items' => $items,
        ];
    }

    public function testCodeIsSetOnConstruction(): void
    {
        $this->assertSame('panth_extra_fee', $this->total()->getCode());
        $this->assertSame('Additional Fees', (string)$this->total()->getLabel());
    }

    public function testNothingHappensWithoutItems(): void
    {
        $this->fees = [$this->fee(1, 5)];
        $total = $this->totals();

        $this->total()->collect(new QuoteDouble(['id' => 3]), $this->assignment($this->address(), []), $total);

        $this->assertSame([], $this->deletes);
        $this->assertSame(0, $total->getTotalAmount('panth_extra_fee'));
    }

    public function testFeesAreSavedAndAddedToTotals(): void
    {
        $this->fees = [$this->fee(1, 5, 0.0, [7 => 5.0]), $this->fee(2, 3)];
        $total = $this->totals();
        $address = $this->address();

        $this->total()->collect(new QuoteDouble(['id' => 3, 'store_id' => 1]), $this->assignment($address), $total);

        $this->assertSame([['panth_extra_fee_quote', ['quote_id = ?' => 3]]], $this->deletes);
        $this->assertCount(2, $this->saved);
        $this->assertSame(3, $this->saved[0]['quote_id']);
        $this->assertSame('{"7":5}', $this->saved[0]['item_breakdown']);
        $this->assertNull($this->saved[1]['item_breakdown']);
        $this->assertSame(0, $this->saved[0]['tax_charged']);
        $this->assertSame(16.0, $total->getTotalAmount('panth_extra_fee'));
        $this->assertSame(8.0, $total->getBaseTotalAmount('panth_extra_fee'));
        $this->assertTrue($address->getData(ExtraFee::CARRIER_FLAG));
    }

    public function testFeeTaxIsAddedOnlyWhenChargingTax(): void
    {
        $this->fees = [$this->fee(1, 10, 2)];

        $noTax = $this->totals();
        $this->total()->collect(new QuoteDouble(['id' => 3]), $this->assignment($this->address()), $noTax);
        $this->assertSame(0, $noTax->getTotalAmount('tax'));

        $this->chargeTax = true;
        $withTax = $this->totals();
        $this->total()->collect(new QuoteDouble(['id' => 3]), $this->assignment($this->address()), $withTax);
        $this->assertSame(4.0, $withTax->getTotalAmount('tax'));
        $this->assertSame(2.0, $withTax->getBaseTotalAmount('tax'));
        $this->assertSame(1, $this->saved[1]['tax_charged']);
    }

    public function testUnsavedQuoteKeepsFeesPendingInsteadOfSaving(): void
    {
        $this->fees = [$this->fee(1, 5)];
        $quote = new QuoteDouble(['store_id' => 1]);
        $total = $this->totals();

        $this->total()->collect($quote, $this->assignment($this->address()), $total);

        $this->assertSame([], $this->saved);
        $this->assertSame([], $this->deletes);
        $this->assertSame(['fees' => $this->fees, 'charge_tax' => false], $quote->getData(ExtraFee::PENDING_FEES_KEY));
        $this->assertSame(10.0, $total->getTotalAmount('panth_extra_fee'));
    }

    public function testFeesAreClearedButNotRecalculatedWhenNotApplicable(): void
    {
        $this->apply = false;
        $this->fees = [$this->fee(1, 5)];
        $total = $this->totals();

        $this->total()->collect(new QuoteDouble(['id' => 3]), $this->assignment($this->address()), $total);

        $this->assertCount(1, $this->deletes);
        $this->assertSame([], $this->saved);
        $this->assertSame(0, $total->getTotalAmount('panth_extra_fee'));
    }

    public function testCalculatorErrorsAreLogged(): void
    {
        $this->calculatorException = new \RuntimeException('boom');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('boom'));

        $this->total($logger)->collect(
            new QuoteDouble(['id' => 3]),
            $this->assignment($this->address()),
            $this->totals()
        );
    }

    public function testMultishippingChargesOnlyTheFirstAddressWithItems(): void
    {
        $this->fees = [$this->fee(1, 5)];
        $empty = $this->address(10, 0);
        $first = $this->address(11, 2);
        $second = $this->address(12, 1);
        $quote = new QuoteDouble(['id' => 3, 'is_multi_shipping' => 1]);
        $quote->shippingAddresses = [$empty, $first, $second];

        $firstTotal = $this->totals();
        $this->total()->collect($quote, $this->assignment($first), $firstTotal);
        $secondTotal = $this->totals();
        $this->total()->collect($quote, $this->assignment($second), $secondTotal);

        $this->assertTrue($first->getData(ExtraFee::CARRIER_FLAG));
        $this->assertFalse($second->getData(ExtraFee::CARRIER_FLAG));
        $this->assertSame(10.0, $firstTotal->getTotalAmount('panth_extra_fee'));
        $this->assertSame(0, $secondTotal->getTotalAmount('panth_extra_fee'));
    }

    public function testMultishippingMatchesTheCarrierById(): void
    {
        $this->fees = [$this->fee(1, 5)];
        $stored = $this->address(11, 1);
        $reloaded = $this->address(11, 1);
        $quote = new QuoteDouble(['id' => 3, 'is_multi_shipping' => 1]);
        $quote->shippingAddresses = [$stored];

        $this->total()->collect($quote, $this->assignment($reloaded), $this->totals());

        $this->assertTrue($reloaded->getData(ExtraFee::CARRIER_FLAG));
    }

    public function testMultishippingWithoutShippingItemsUsesTheBillingAddress(): void
    {
        $quote = new QuoteDouble(['id' => 3, 'is_multi_shipping' => 1]);
        $quote->shippingAddresses = [$this->address(10, 0)];
        $billing = $this->address(20, 1, Address::ADDRESS_TYPE_BILLING);
        $shipping = $this->address(21, 1, Address::ADDRESS_TYPE_SHIPPING);

        $this->total()->collect($quote, $this->assignment($billing), $this->totals());
        $this->total()->collect($quote, $this->assignment($shipping), $this->totals());

        $this->assertTrue($billing->getData(ExtraFee::CARRIER_FLAG));
        $this->assertFalse($shipping->getData(ExtraFee::CARRIER_FLAG));
    }

    public function testFetchReturnsOneSegmentPerPositiveStoredFee(): void
    {
        $this->storedFees = [
            $this->newWithoutConstructor(QuoteFee::class, ['rule_id' => 4, 'fee_label' => 'Packing', 'fee_amount' => 2.5]),
            $this->newWithoutConstructor(QuoteFee::class, ['rule_id' => 5, 'fee_label' => 'Zero', 'fee_amount' => 0]),
        ];

        $segments = $this->total()->fetch(new QuoteDouble(['id' => 3]), $this->totals());

        $this->assertCount(1, $segments);
        $this->assertSame('panth_extra_fee_4', $segments[0]['code']);
        $this->assertSame('Packing', (string)$segments[0]['title']);
        $this->assertSame(2.5, $segments[0]['value']);
    }

    public function testFetchIsEmptyWhenNotApplicableOrUnsaved(): void
    {
        $this->storedFees = [
            $this->newWithoutConstructor(QuoteFee::class, ['rule_id' => 4, 'fee_label' => 'Packing', 'fee_amount' => 2.5]),
        ];

        $this->assertSame([], $this->total()->fetch(new QuoteDouble([]), $this->totals()));

        $this->apply = false;
        $this->assertSame([], $this->total()->fetch(new QuoteDouble(['id' => 3]), $this->totals()));
    }

    public function testFetchIsEmptyWithoutStoredFees(): void
    {
        $this->assertSame([], $this->total()->fetch(new QuoteDouble(['id' => 3]), $this->totals()));
    }

    public function testPersistPendingFeesWritesThemOnceTheQuoteHasAnId(): void
    {
        $quote = new QuoteDouble(['id' => 8]);
        $quote->setData(ExtraFee::PENDING_FEES_KEY, ['fees' => [$this->fee(1, 5)], 'charge_tax' => true]);

        $this->total()->persistPendingFees($quote);

        $this->assertNull($quote->getData(ExtraFee::PENDING_FEES_KEY));
        $this->assertSame([['panth_extra_fee_quote', ['quote_id = ?' => 8]]], $this->deletes);
        $this->assertCount(1, $this->saved);
        $this->assertSame(1, $this->saved[0]['tax_charged']);
        $this->assertSame(8, $this->saved[0]['quote_id']);
    }

    public function testPersistPendingFeesIgnoresMissingDataOrId(): void
    {
        $unsaved = new QuoteDouble([]);
        $unsaved->setData(ExtraFee::PENDING_FEES_KEY, ['fees' => [$this->fee(1, 5)]]);

        $this->total()->persistPendingFees($unsaved);
        $this->total()->persistPendingFees(new QuoteDouble(['id' => 8]));

        $this->assertNull($unsaved->getData(ExtraFee::PENDING_FEES_KEY));
        $this->assertSame([], $this->saved);
        $this->assertSame([], $this->deletes);
    }

    public function testSaveErrorsAreLoggedAndDoNotStopCollection(): void
    {
        $this->fees = [$this->fee(1, 5)];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('Error saving quote fee'));

        $calculator = $this->createStub(FeeCalculator::class);
        $calculator->method('calculateFees')->willReturn($this->fees);
        $resource = $this->createStub(QuoteFeeResource::class);
        $resource->method('getConnection')->willReturn($this->createStub(AdapterInterface::class));
        $resource->method('save')->willThrowException(new \RuntimeException('db down'));
        $factory = $this->createStub(QuoteFeeFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newWithoutConstructor(QuoteFee::class));
        $helper = $this->createStub(Helper::class);
        $helper->method('shouldApplyFees')->willReturn(true);

        $subject = new ExtraFee(
            $calculator,
            $resource,
            $factory,
            $this->createStub(QuoteFeeCollectionFactory::class),
            $helper,
            $logger,
            new Json()
        );
        $total = $this->totals();
        $subject->collect(new QuoteDouble(['id' => 3]), $this->assignment($this->address()), $total);

        $this->assertSame(10.0, $total->getTotalAmount('panth_extra_fee'));
    }
}
