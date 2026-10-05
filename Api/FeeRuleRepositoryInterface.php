<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;

interface FeeRuleRepositoryInterface
{
    /**
     * Save fee rule
     *
     * @param FeeRuleInterface $feeRule
     * @return FeeRuleInterface
     * @throws CouldNotSaveException
     */
    public function save(FeeRuleInterface $feeRule): FeeRuleInterface;

    /**
     * Get fee rule by ID
     *
     * @param int $ruleId
     * @return FeeRuleInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): FeeRuleInterface;

    /**
     * Get fee rule list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete fee rule
     *
     * @param FeeRuleInterface $feeRule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(FeeRuleInterface $feeRule): bool;

    /**
     * Delete fee rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $ruleId): bool;
}
