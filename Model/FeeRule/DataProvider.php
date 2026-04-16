<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\FeeRule;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var DataPersistorInterface
     */
    private DataPersistorInterface $dataPersistor;

    /**
     * @var array|null
     */
    private ?array $loadedData = null;

    /**
     * Comma-separated fields that need to be converted to arrays for multiselect
     */
    private const MULTI_VALUE_FIELDS = [
        'payment_methods',
        'customer_groups',
        'countries',
        'regions',
        'store_ids',
        'website_ids',
        'product_ids',
        'product_skus',
        'category_ids',
    ];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        foreach ($items as $rule) {
            $ruleData = $rule->getData();
            $ruleData = $this->convertMultiValueFields($ruleData);
            $this->loadedData[$rule->getId()] = $ruleData;
        }

        $persistedData = $this->dataPersistor->get('panth_extra_fee_rule');
        if (!empty($persistedData)) {
            $rule = $this->collection->getNewEmptyItem();
            $rule->setData($persistedData);
            $ruleData = $rule->getData();
            $ruleData = $this->convertMultiValueFields($ruleData);
            $this->loadedData[$rule->getId()] = $ruleData;
            $this->dataPersistor->clear('panth_extra_fee_rule');
        }

        return $this->loadedData;
    }

    /**
     * Convert comma-separated multi-value fields to arrays for multiselect form elements
     *
     * @param array $data
     * @return array
     */
    private function convertMultiValueFields(array $data): array
    {
        foreach (self::MULTI_VALUE_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                $data[$field] = explode(',', $data[$field]);
            }
        }

        return $data;
    }
}
