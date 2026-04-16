<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Sales\Order\Items\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory as FeeRuleCollectionFactory;

class ExtraFee extends DefaultColumn
{
    private ?array $feeRulesCache = null;
    private array $categoryCache = [];

    private FeeRuleCollectionFactory $feeRuleCollectionFactory;
    private ResourceConnection $resourceConnection;

    /**
     * We override _construct instead of __construct to avoid complex parent constructor issues.
     */
    protected function _construct(): void
    {
        parent::_construct();
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->feeRuleCollectionFactory = $om->get(FeeRuleCollectionFactory::class);
        $this->resourceConnection = $om->get(ResourceConnection::class);
    }

    /**
     * Calculate extra fee for this specific order item.
     */
    public function getItemExtraFee(): float
    {
        $item = $this->getItem();
        if (!$item) {
            return 0.0;
        }

        $productId = (int)$item->getProductId();
        $sku = (string)$item->getSku();
        $qty = (float)$item->getQtyOrdered();

        if ($productId === 0) {
            return 0.0;
        }

        $itemCategories = $this->getItemCategories($productId);
        $rules = $this->getPerItemFeeRules();
        $totalFee = 0.0;

        foreach ($rules as $rule) {
            if (!$this->itemMatchesRule($rule, $productId, $sku, $itemCategories)) {
                continue;
            }

            $feeAmount = (float)$rule->getFeeAmount();
            $applyPer = (string)$rule->getApplyPer();

            if ($applyPer === 'product') {
                $totalFee += $feeAmount;
            } elseif ($applyPer === 'quantity') {
                $totalFee += $feeAmount * $qty;
            }
        }

        return $totalFee;
    }

    /**
     * Format the fee amount with order currency.
     */
    public function getFormattedItemExtraFee(): string
    {
        $fee = $this->getItemExtraFee();
        if ($fee <= 0.0) {
            return '';
        }

        $order = $this->getOrder();
        $currencyCode = $order ? (string)$order->getOrderCurrencyCode() : 'USD';

        $priceCurrency = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(PriceCurrencyInterface::class);

        return $priceCurrency->format(
            $fee,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    private function getPerItemFeeRules(): array
    {
        if ($this->feeRulesCache !== null) {
            return $this->feeRulesCache;
        }

        $collection = $this->feeRuleCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addFieldToFilter('apply_per', ['in' => ['product', 'quantity']]);

        $this->feeRulesCache = $collection->getItems();
        return $this->feeRulesCache;
    }

    private function itemMatchesRule($rule, int $productId, string $sku, array $itemCategories): bool
    {
        $ruleProductIds = array_filter(array_map('intval', explode(',', (string)$rule->getData('product_ids'))));
        if (!empty($ruleProductIds) && !in_array($productId, $ruleProductIds, true)) {
            return false;
        }

        $ruleSkus = array_filter(array_map('trim', explode(',', (string)$rule->getData('product_skus'))));
        if (!empty($ruleSkus) && !in_array($sku, $ruleSkus, true)) {
            return false;
        }

        $ruleCategoryIds = array_filter(array_map('intval', explode(',', (string)$rule->getData('category_ids'))));
        if (!empty($ruleCategoryIds) && empty(array_intersect($ruleCategoryIds, $itemCategories))) {
            return false;
        }

        return true;
    }

    private function getItemCategories(int $productId): array
    {
        if (isset($this->categoryCache[$productId])) {
            return $this->categoryCache[$productId];
        }

        $conn = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_category_product');
        $categories = $conn->fetchCol(
            $conn->select()->from($table, ['category_id'])->where('product_id = ?', $productId)
        );

        $this->categoryCache[$productId] = array_map('intval', $categories);
        return $this->categoryCache[$productId];
    }
}
