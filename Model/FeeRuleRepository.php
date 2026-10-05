<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Model\FeeRuleFactory;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;

class FeeRuleRepository implements FeeRuleRepositoryInterface
{
    private FeeRuleFactory $feeRuleFactory;

    private FeeRuleResource $resource;

    private CollectionFactory $collectionFactory;

    private CollectionProcessorInterface $collectionProcessor;

    private SearchResultsInterfaceFactory $searchResultsFactory;

    public function __construct(
        FeeRuleFactory $feeRuleFactory,
        FeeRuleResource $resource,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->feeRuleFactory = $feeRuleFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(FeeRuleInterface $feeRule): FeeRuleInterface
    {
        try {
            $this->resource->save($feeRule);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the fee rule: %1', $exception->getMessage()),
                $exception
            );
        }

        return $feeRule;
    }

    public function getById(int $ruleId): FeeRuleInterface
    {
        $feeRule = $this->feeRuleFactory->create();
        $this->resource->load($feeRule, $ruleId);

        if (!$feeRule->getRuleId()) {
            throw new NoSuchEntityException(
                __('The fee rule with ID "%1" does not exist.', $ruleId)
            );
        }

        return $feeRule;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function delete(FeeRuleInterface $feeRule): bool
    {
        try {
            $this->resource->delete($feeRule);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the fee rule: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    public function deleteById(int $ruleId): bool
    {
        return $this->delete($this->getById($ruleId));
    }
}
