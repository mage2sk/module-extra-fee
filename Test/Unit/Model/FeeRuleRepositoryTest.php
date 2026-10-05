<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\FeeRuleFactory;
use Panth\ExtraFee\Model\FeeRuleRepository;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

class FeeRuleRepositoryTest extends TestCase
{
    use ObjectHelperTrait;

    private array $rows = [5 => ['rule_id' => 5, 'name' => 'Packing']];

    private function repository(?FeeRuleResource $resource = null, ?Collection $collection = null): FeeRuleRepository
    {
        if ($resource === null) {
            $resource = $this->createStub(FeeRuleResource::class);
            $resource->method('load')->willReturnCallback(function ($rule, $id) use ($resource) {
                if (isset($this->rows[$id])) {
                    $rule->setData($this->rows[$id]);
                }
                return $resource;
            });
        }
        $factory = $this->createStub(FeeRuleFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newWithoutConstructor(FeeRule::class));

        $searchResultsFactory = $this->createStub(SearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')->willReturnCallback(static fn() => new SearchResults());

        return new FeeRuleRepository(
            $factory,
            $resource,
            $this->factoryReturning(CollectionFactory::class, $collection ?? $this->createStub(Collection::class)),
            $this->createStub(CollectionProcessorInterface::class),
            $searchResultsFactory
        );
    }

    public function testGetByIdReturnsTheLoadedRule(): void
    {
        $rule = $this->repository()->getById(5);

        $this->assertSame(5, $rule->getRuleId());
        $this->assertSame('Packing', $rule->getName());
    }

    public function testGetByIdThrowsForUnknownRule(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('The fee rule with ID "9" does not exist.');

        $this->repository()->getById(9);
    }

    public function testSaveReturnsTheRule(): void
    {
        $resource = $this->createMock(FeeRuleResource::class);
        $rule = $this->newWithoutConstructor(FeeRule::class, ['name' => 'New']);
        $resource->expects($this->once())->method('save')->with($rule);

        $this->assertSame($rule, $this->repository($resource)->save($rule));
    }

    public function testSaveWrapsResourceErrors(): void
    {
        $resource = $this->createStub(FeeRuleResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('duplicate'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save the fee rule: duplicate');

        $this->repository($resource)->save($this->newWithoutConstructor(FeeRule::class));
    }

    public function testDeleteWrapsResourceErrors(): void
    {
        $resource = $this->createStub(FeeRuleResource::class);
        $resource->method('delete')->willThrowException(new \RuntimeException('locked'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete the fee rule: locked');

        $this->repository($resource)->delete($this->newWithoutConstructor(FeeRule::class));
    }

    public function testDeleteByIdLoadsThenDeletes(): void
    {
        $resource = $this->createMock(FeeRuleResource::class);
        $resource->method('load')->willReturnCallback(function ($rule) use ($resource) {
            $rule->setData(['rule_id' => 5]);
            return $resource;
        });
        $resource->expects($this->once())->method('delete')
            ->with($this->callback(static fn($rule) => $rule->getRuleId() === 5));

        $this->assertTrue($this->repository($resource)->deleteById(5));
    }

    public function testGetListFillsSearchResults(): void
    {
        $rules = [$this->newWithoutConstructor(FeeRule::class, ['rule_id' => 1])];
        $collection = $this->collectionOf(Collection::class, $rules, 12);
        $criteria = $this->createStub(SearchCriteriaInterface::class);

        $results = $this->repository(null, $collection)->getList($criteria);

        $this->assertSame($criteria, $results->getSearchCriteria());
        $this->assertSame($rules, $results->getItems());
        $this->assertSame(12, $results->getTotalCount());
    }
}
