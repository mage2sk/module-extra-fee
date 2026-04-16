<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\ResourceModel\FeeRule\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Psr\Log\LoggerInterface;

/**
 * Grid collection for fee rules admin UI
 *
 * Note: When creating the UI component XML, use:
 * <argument name="storageConfig" xsi:type="array">
 *     <item name="indexField" xsi:type="string">rule_id</item>
 * </argument>
 */
class Collection extends \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult implements SearchResultInterface
{
    /**
     * @var string
     */
    protected $_idFieldName = 'rule_id';

    /**
     * @var AggregationInterface
     */
    protected $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'panth_extra_fee_rule',
        string $resourceModel = \Panth\ExtraFee\Model\ResourceModel\FeeRule::class,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $connection,
            $resource
        );
    }

    /**
     * @inheritdoc
     */
    protected function _initSelect(): self
    {
        parent::_initSelect();
        $this->addFilterToMap('rule_id', 'main_table.rule_id');
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad(): self
    {
        parent::_afterLoad();
        foreach ($this->_items as $item) {
            $item->setId($item->getData('rule_id'));
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * @inheritdoc
     */
    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria = null): self
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * @inheritdoc
     */
    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items = null): self
    {
        return $this;
    }
}
