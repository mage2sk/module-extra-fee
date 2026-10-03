<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\FeeRule;

use Magento\Framework\App\Request\DataPersistorInterface;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\FeeRule\DataProvider;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    use ObjectHelperTrait;

    private function rule(array $data): FeeRule
    {
        $rule = $this->newWithoutConstructor(FeeRule::class, $data);
        $rule->setIdFieldName('rule_id');
        return $rule;
    }

    private function provider(array $rules, $persisted, ?DataPersistorInterface $persistor = null): DataProvider
    {
        $collection = $this->collectionOf(Collection::class, $rules);
        $collection->method('getNewEmptyItem')->willReturnCallback(fn() => $this->rule([]));

        if ($persistor === null) {
            $persistor = $this->createStub(DataPersistorInterface::class);
            $persistor->method('get')->willReturn($persisted);
        }

        return new DataProvider(
            'panth_extrafee_rule_form_data_source',
            'rule_id',
            'rule_id',
            $this->factoryReturning(CollectionFactory::class, $collection),
            $persistor
        );
    }

    public function testRulesAreKeyedByIdWithListFieldsExploded(): void
    {
        $data = $this->provider([
            $this->rule([
                'rule_id' => 3,
                'name' => 'COD',
                'payment_methods' => 'cashondelivery,checkmo',
                'customer_groups' => '0,1',
                'regions' => '12',
                'product_skus' => 'a,b',
                'countries' => '',
                'description' => 'x,y',
            ]),
        ], null)->getData();

        $this->assertSame(['cashondelivery', 'checkmo'], $data[3]['payment_methods']);
        $this->assertSame(['0', '1'], $data[3]['customer_groups']);
        $this->assertSame(['12'], $data[3]['regions']);
        $this->assertSame(['a', 'b'], $data[3]['product_skus']);
        $this->assertSame('', $data[3]['countries']);
        $this->assertSame('x,y', $data[3]['description']);
    }

    public function testPersistedFormDataIsRestoredOnceAndCleared(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->method('get')->willReturn(['rule_id' => 8, 'name' => 'Draft', 'store_ids' => '1,2']);
        $persistor->expects($this->once())->method('clear')->with('panth_extra_fee_rule');

        $provider = $this->provider([], null, $persistor);
        $data = $provider->getData();

        $this->assertSame('Draft', $data[8]['name']);
        $this->assertSame(['1', '2'], $data[8]['store_ids']);
        $this->assertSame($data, $provider->getData());
    }

    public function testEmptyCollectionGivesEmptyData(): void
    {
        $this->assertSame([], $this->provider([], [])->getData());
    }
}
