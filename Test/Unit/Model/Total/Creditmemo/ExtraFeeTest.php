<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Total\Creditmemo;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Creditmemo;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Model\Total\Creditmemo\ExtraFee;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExtraFeeTest extends TestCase
{
    use ObjectHelperTrait;

    private array $refundable = [];

    private function creditmemo(int $orderId, bool $isLast = false): Creditmemo
    {
        $creditmemo = new class extends Creditmemo {
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
        $creditmemo->orderDouble = new DataObject(['id' => $orderId]);
        $creditmemo->lastDouble = $isLast;
        $creditmemo->setData([
            'grand_total' => 100.0,
            'base_grand_total' => 100.0,
            'tax_amount' => 10.0,
            'base_tax_amount' => 10.0,
        ]);
        return $creditmemo;
    }

    private function orderFee(array $data): OrderFee
    {
        $fee = $this->newWithoutConstructor(OrderFee::class, $data + [
            'entity_id' => 1,
            'rule_id' => 1,
            'fee_type' => 'fixed',
            'fee_label' => 'Handling',
            'base_fee_amount' => 10,
            'fee_amount' => 10,
            'base_fee_invoiced' => 10,
            'fee_invoiced' => 10,
            'base_fee_refunded' => 0,
            'fee_refunded' => 0,
            'base_tax_amount' => 0,
            'tax_amount' => 0,
            'base_tax_refunded' => 0,
            'tax_refunded' => 0,
        ]);
        $fee->setIdFieldName('entity_id');
        return $fee;
    }

    private function collector(array $fees, ?LoggerInterface $logger = null): ExtraFee
    {
        $collection = $this->collectionOf(Collection::class, $fees);
        $collection->method('addFieldToFilter')->willReturnSelf();

        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function (int $id) {
            if (!array_key_exists($id, $this->refundable)) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->newWithoutConstructor(FeeRule::class, ['rule_id' => $id, 'is_refundable' => $this->refundable[$id]]);
        });

        return new ExtraFee(
            $this->factoryReturning(CollectionFactory::class, $collection),
            $repository,
            $logger ?? $this->createStub(LoggerInterface::class),
            new Json()
        );
    }

    public function testCreditmemoWithoutOrderIsLeftAlone(): void
    {
        $creditmemo = $this->creditmemo(0);

        $this->collector([$this->orderFee([])])->collect($creditmemo);

        $this->assertSame([], $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY));
        $this->assertSame(100.0, $creditmemo->getGrandTotal());
    }

    public function testRefundableInvoicedFeeIsRefunded(): void
    {
        $this->refundable = [1 => 1];
        $creditmemo = $this->creditmemo(3);

        $this->collector([$this->orderFee(['entity_id' => 21, 'base_fee_refunded' => 4, 'fee_refunded' => 4])])
            ->collect($creditmemo);

        $this->assertSame(106.0, $creditmemo->getGrandTotal());
        $this->assertSame(6.0, $creditmemo->getData('panth_extra_fee_amount'));
        $this->assertSame(
            [21 => ['base_fee' => 6.0, 'fee' => 6.0, 'base_tax' => 0.0, 'tax' => 0.0]],
            $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY)
        );
        $this->assertSame('Handling', json_decode($creditmemo->getData(DocumentFeeTotals::DETAILS_KEY), true)[0]['label']);
    }

    public function testNonRefundableRulesAreSkipped(): void
    {
        $this->refundable = [1 => 0];
        $creditmemo = $this->creditmemo(3);

        $this->collector([$this->orderFee([])])->collect($creditmemo);

        $this->assertSame([], $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY));
        $this->assertSame(100.0, $creditmemo->getGrandTotal());
    }

    public function testDeletedRulesAndSmallOrderFeesAreRefundable(): void
    {
        $this->refundable = [5 => 0];
        $creditmemo = $this->creditmemo(3);
        $fees = [
            $this->orderFee(['entity_id' => 1, 'rule_id' => 99]),
            $this->orderFee(['entity_id' => 2, 'rule_id' => 5, 'fee_type' => 'small_order']),
            $this->orderFee(['entity_id' => 3, 'rule_id' => 0]),
        ];

        $this->collector($fees)->collect($creditmemo);

        $this->assertSame([1, 2, 3], array_keys($creditmemo->getData(ExtraFee::ALLOCATIONS_KEY)));
        $this->assertSame(130.0, $creditmemo->getGrandTotal());
    }

    public function testFullyRefundedOrUninvoicedFeesAreSkipped(): void
    {
        $this->refundable = [1 => 1];
        $creditmemo = $this->creditmemo(3);
        $fees = [
            $this->orderFee(['entity_id' => 1, 'base_fee_refunded' => 10, 'fee_refunded' => 10]),
            $this->orderFee(['entity_id' => 2, 'base_fee_invoiced' => 0, 'fee_invoiced' => 0]),
        ];

        $this->collector($fees)->collect($creditmemo);

        $this->assertSame([], $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY));
        $this->assertSame('[]', $creditmemo->getData(DocumentFeeTotals::DETAILS_KEY));
    }

    public function testRefundTaxIsProportionalAndCappedByWhatIsLeft(): void
    {
        $this->refundable = [1 => 1];
        $creditmemo = $this->creditmemo(3);
        $fee = $this->orderFee([
            'base_tax_amount' => 2,
            'tax_amount' => 2,
            'base_tax_refunded' => 1.5,
            'tax_refunded' => 1.5,
        ]);

        $this->collector([$fee])->collect($creditmemo);

        $allocation = $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY)[1];
        $this->assertSame(0.5, $allocation['base_tax']);
        $this->assertSame(0.5, $allocation['tax']);
    }

    public function testRefundTaxNeverGoesNegative(): void
    {
        $this->refundable = [1 => 1];
        $creditmemo = $this->creditmemo(3);
        $fee = $this->orderFee([
            'base_tax_amount' => 2,
            'tax_amount' => 2,
            'base_tax_refunded' => 3,
            'tax_refunded' => 3,
        ]);

        $this->collector([$fee])->collect($creditmemo);

        $this->assertSame(0.0, $creditmemo->getData(ExtraFee::ALLOCATIONS_KEY)[1]['tax']);
    }

    public function testChargedTaxIsAddedUnlessLastCreditmemo(): void
    {
        $this->refundable = [1 => 1];
        $fee = ['base_tax_amount' => 2, 'tax_amount' => 2, 'tax_charged' => 1];

        $partial = $this->creditmemo(3, false);
        $this->collector([$this->orderFee($fee)])->collect($partial);
        $this->assertSame(12.0, $partial->getTaxAmount());
        $this->assertSame(112.0, $partial->getGrandTotal());

        $last = $this->creditmemo(3, true);
        $this->collector([$this->orderFee($fee)])->collect($last);
        $this->assertSame(10.0, $last->getTaxAmount());
        $this->assertSame(110.0, $last->getGrandTotal());
    }

    public function testErrorsAreLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('creditmemo'));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('down'));

        $collector = new ExtraFee(
            $factory,
            $this->createStub(FeeRuleRepositoryInterface::class),
            $logger,
            new Json()
        );
        $collector->collect($this->creditmemo(3));
    }
}
