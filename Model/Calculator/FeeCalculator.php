<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Calculator;

use Magento\Quote\Model\Quote;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory as FeeRuleCollectionFactory;
use Psr\Log\LoggerInterface;

class FeeCalculator
{
    /**
     * @var FeeRuleCollectionFactory
     */
    private FeeRuleCollectionFactory $feeRuleCollectionFactory;

    /**
     * @var ConditionChecker
     */
    private ConditionChecker $conditionChecker;

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var TaxCalculation
     */
    private TaxCalculation $taxCalculation;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param FeeRuleCollectionFactory $feeRuleCollectionFactory
     * @param ConditionChecker $conditionChecker
     * @param Helper $helper
     * @param TaxCalculation $taxCalculation
     * @param LoggerInterface $logger
     */
    public function __construct(
        FeeRuleCollectionFactory $feeRuleCollectionFactory,
        ConditionChecker $conditionChecker,
        Helper $helper,
        TaxCalculation $taxCalculation,
        LoggerInterface $logger
    ) {
        $this->feeRuleCollectionFactory = $feeRuleCollectionFactory;
        $this->conditionChecker = $conditionChecker;
        $this->helper = $helper;
        $this->taxCalculation = $taxCalculation;
        $this->logger = $logger;
    }

    /**
     * Calculate all applicable fees for a quote.
     *
     * @param Quote $quote
     * @return array
     */
    public function calculateFees(Quote $quote): array
    {
        $storeId = (int)$quote->getStoreId();

        if (!$this->helper->isEnabled($storeId)) {
            return [];
        }

        if (!$quote->getItemsCount()) {
            $this->debugLog('Quote has no items, skipping fee calculation', $storeId);
            return [];
        }

        $fees = [];

        // Check small order fee first
        $smallOrderFee = $this->calculateSmallOrderFee($quote);
        if ($smallOrderFee !== null) {
            $fees[] = $smallOrderFee;
            $this->debugLog(
                sprintf('Small order fee applied: %s', $smallOrderFee['base_amount']),
                $storeId
            );
        }

        // Load active fee rules ordered by sort_order
        /** @var \Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection $collection */
        $collection = $this->feeRuleCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->setOrder('sort_order', 'ASC');

        /** @var FeeRule $rule */
        foreach ($collection as $rule) {
            if (!$this->conditionChecker->isRuleValid($rule, $quote)) {
                $this->debugLog(
                    sprintf('Rule #%d "%s" conditions not met, skipping', $rule->getRuleId(), $rule->getName()),
                    $storeId
                );
                continue;
            }

            $baseAmount = $this->calculateAmount($rule, $quote);

            // Apply min/max fee constraints
            $baseAmount = $this->applyMinMaxConstraints($rule, $baseAmount);

            // Skip zero amounts unless configured to show
            if ($baseAmount <= 0.0 && !$this->helper->isShowZeroFees($storeId)) {
                $this->debugLog(
                    sprintf('Rule #%d "%s" resulted in zero fee, skipping', $rule->getRuleId(), $rule->getName()),
                    $storeId
                );
                continue;
            }

            // Calculate tax
            $baseTax = 0.0;
            $taxClassId = (int)$rule->getTaxClassId();
            if ($taxClassId > 0 && $baseAmount > 0.0) {
                $baseTax = $this->calculateTax($baseAmount, $taxClassId, $quote);
            }

            $fees[] = [
                'rule_id'     => (int)$rule->getRuleId(),
                'label'       => (string)$rule->getFeeLabel(),
                'fee_type'    => (string)$rule->getFeeType(),
                'base_amount' => round($baseAmount, 4),
                'amount'      => round($baseAmount, 4),
                'base_tax'    => round($baseTax, 4),
                'tax'         => round($baseTax, 4),
            ];

            $this->debugLog(
                sprintf(
                    'Rule #%d "%s" applied: base_amount=%.4f, base_tax=%.4f',
                    $rule->getRuleId(),
                    $rule->getName(),
                    $baseAmount,
                    $baseTax
                ),
                $storeId
            );

            // Stop processing further rules if flag is set
            if ($rule->getStopFurtherRules()) {
                $this->debugLog(
                    sprintf('Rule #%d has stop_further_rules, breaking', $rule->getRuleId()),
                    $storeId
                );
                break;
            }
        }

        // Apply global maximum fee cap
        $fees = $this->applyGlobalMaxCap($fees, $storeId);

        return $fees;
    }

    /**
     * Calculate the fee amount for a given rule and quote.
     *
     * @param FeeRuleInterface $rule
     * @param Quote $quote
     * @return float
     */
    public function calculateAmount(FeeRuleInterface $rule, Quote $quote): float
    {
        $feeType = (string)$rule->getFeeType();
        $feeAmount = (float)$rule->getFeeAmount();
        $feeAmountPercent = (float)$rule->getFeeAmountPercent();
        $applyPer = (string)$rule->getApplyPer() ?: 'order';
        $storeId = (int)$quote->getStoreId();

        $subtotal = $this->getSubtotal($quote, $storeId);

        switch ($feeType) {
            case 'fixed':
                return $this->calculateFixedAmount($feeAmount, $applyPer, $rule, $quote);

            case 'percent':
                return $subtotal * $feeAmountPercent / 100;

            case 'combined':
                $fixedPart = $this->calculateFixedAmount($feeAmount, $applyPer, $rule, $quote);
                $percentPart = $subtotal * $feeAmountPercent / 100;
                return $fixedPart + $percentPart;

            case 'fixed_minimum':
                $fixedPart = $this->calculateFixedAmount($feeAmount, $applyPer, $rule, $quote);
                $percentPart = $subtotal * $feeAmountPercent / 100;
                return max($fixedPart, $percentPart);

            default:
                $this->logger->warning(
                    sprintf('Panth_ExtraFee: Unknown fee_type "%s" for rule #%d', $feeType, $rule->getRuleId())
                );
                return 0.0;
        }
    }

    /**
     * Calculate small order fee if applicable.
     *
     * @param Quote $quote
     * @return array|null
     */
    public function calculateSmallOrderFee(Quote $quote): ?array
    {
        $storeId = (int)$quote->getStoreId();

        if (!$this->helper->isSmallOrderFeeEnabled($storeId)) {
            return null;
        }

        $minimumAmount = $this->helper->getSmallOrderMinAmount($storeId);
        $subtotal = $this->getSubtotal($quote, $storeId);

        if ($subtotal >= $minimumAmount) {
            return null;
        }

        $feeType = $this->helper->getSmallOrderFeeType($storeId);
        $feeAmount = $this->helper->getSmallOrderFeeAmount($storeId);

        if ($feeAmount <= 0.0) {
            return null;
        }

        $baseAmount = $feeType === 'percent'
            ? $subtotal * $feeAmount / 100
            : $feeAmount;

        $baseTax = 0.0;
        $taxClassId = $this->helper->getSmallOrderTaxClassId($storeId);
        if ($taxClassId > 0 && $baseAmount > 0.0) {
            $baseTax = $this->calculateTax($baseAmount, $taxClassId, $quote);
        }

        return [
            'rule_id'     => 0,
            'label'       => $this->helper->getSmallOrderFeeLabel($storeId) ?: __('Small Order Fee')->render(),
            'fee_type'    => 'small_order',
            'base_amount' => round($baseAmount, 4),
            'amount'      => round($baseAmount, 4),
            'base_tax'    => round($baseTax, 4),
            'tax'         => round($baseTax, 4),
        ];
    }

    /**
     * Calculate tax amount for a fee using Magento tax calculation.
     *
     * @param float $feeAmount
     * @param int $taxClassId
     * @param Quote $quote
     * @return float
     */
    public function calculateTax(float $feeAmount, int $taxClassId, Quote $quote): float
    {
        if ($feeAmount <= 0.0 || $taxClassId <= 0) {
            return 0.0;
        }

        try {
            $billingAddress = $quote->getBillingAddress();
            $shippingAddress = $quote->getShippingAddress();
            $customerTaxClassId = $quote->getCustomerTaxClassId();

            $request = $this->taxCalculation->getRateRequest(
                $shippingAddress,
                $billingAddress,
                $customerTaxClassId,
                $quote->getStore()
            );
            $request->setProductClassId($taxClassId);

            $taxRate = $this->taxCalculation->getRate($request);

            if ($taxRate > 0) {
                return $feeAmount * $taxRate / 100;
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Panth_ExtraFee: Tax calculation error: %s', $e->getMessage())
            );
        }

        return 0.0;
    }

    /**
     * Calculate fixed amount based on apply_per setting.
     *
     * @param float $feeAmount
     * @param string $applyPer
     * @param FeeRuleInterface $rule
     * @param Quote $quote
     * @return float
     */
    private function calculateFixedAmount(
        float $feeAmount,
        string $applyPer,
        FeeRuleInterface $rule,
        Quote $quote
    ): float {
        switch ($applyPer) {
            case 'product':
                $itemCount = $this->getMatchingItemCount($rule, $quote);
                return $feeAmount * $itemCount;

            case 'quantity':
                $totalQty = $this->getMatchingItemQty($rule, $quote);
                return $feeAmount * $totalQty;

            case 'order':
            default:
                return $feeAmount;
        }
    }

    /**
     * Get the count of distinct quote items matching rule conditions.
     *
     * @param FeeRuleInterface $rule
     * @param Quote $quote
     * @return int
     */
    private function getMatchingItemCount(FeeRuleInterface $rule, Quote $quote): int
    {
        $count = 0;
        $productIds = $this->parseCommaSeparated($rule->getProductIds());
        $productSkus = $this->parseCommaSeparated($rule->getProductSkus());
        $categoryIds = $this->parseCommaSeparated($rule->getCategoryIds());
        $excludeVirtual = $this->helper->isExcludeVirtualProducts((int)$quote->getStoreId());
        $hasConditions = !empty($productIds) || !empty($productSkus) || !empty($categoryIds);

        foreach ($quote->getAllVisibleItems() as $item) {
            if ($excludeVirtual && $item->getIsVirtual()) {
                continue;
            }

            if (!$hasConditions || $this->isItemMatching($item, $productIds, $productSkus, $categoryIds)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the total qty of quote items matching rule conditions.
     *
     * @param FeeRuleInterface $rule
     * @param Quote $quote
     * @return float
     */
    private function getMatchingItemQty(FeeRuleInterface $rule, Quote $quote): float
    {
        $qty = 0.0;
        $productIds = $this->parseCommaSeparated($rule->getProductIds());
        $productSkus = $this->parseCommaSeparated($rule->getProductSkus());
        $categoryIds = $this->parseCommaSeparated($rule->getCategoryIds());
        $excludeVirtual = $this->helper->isExcludeVirtualProducts((int)$quote->getStoreId());
        $hasConditions = !empty($productIds) || !empty($productSkus) || !empty($categoryIds);

        foreach ($quote->getAllVisibleItems() as $item) {
            if ($excludeVirtual && $item->getIsVirtual()) {
                continue;
            }

            if (!$hasConditions || $this->isItemMatching($item, $productIds, $productSkus, $categoryIds)) {
                $qty += (float)$item->getQty();
            }
        }

        return $qty;
    }

    /**
     * Check if a quote item matches product/sku/category conditions.
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param array $productIds
     * @param array $productSkus
     * @param array $categoryIds
     * @return bool
     */
    private function isItemMatching($item, array $productIds, array $productSkus, array $categoryIds): bool
    {
        if (!empty($productIds) && in_array((string)$item->getProductId(), $productIds, true)) {
            return true;
        }

        if (!empty($productSkus) && in_array($item->getSku(), $productSkus, true)) {
            return true;
        }

        if (!empty($categoryIds)) {
            $product = $item->getProduct();
            if ($product) {
                $itemCategoryIds = $product->getCategoryIds();
                if (is_array($itemCategoryIds) && array_intersect($categoryIds, $itemCategoryIds)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply min/max fee constraints from the rule.
     *
     * @param FeeRuleInterface $rule
     * @param float $amount
     * @return float
     */
    private function applyMinMaxConstraints(FeeRuleInterface $rule, float $amount): float
    {
        $minFee = $rule->getMinFeeAmount();
        $maxFee = $rule->getMaxFeeAmount();

        if ($minFee !== null && $minFee > 0 && $amount < $minFee) {
            $amount = $minFee;
        }

        if ($maxFee !== null && $maxFee > 0 && $amount > $maxFee) {
            $amount = $maxFee;
        }

        return $amount;
    }

    /**
     * Apply global maximum total fee cap if configured.
     *
     * @param array $fees
     * @param int $storeId
     * @return array
     */
    private function applyGlobalMaxCap(array $fees, int $storeId): array
    {
        $maxTotalFee = $this->helper->getMaxTotalFee($storeId);
        if ($maxTotalFee === null || $maxTotalFee <= 0.0 || empty($fees)) {
            return $fees;
        }

        $totalBaseAmount = 0.0;
        foreach ($fees as $fee) {
            $totalBaseAmount += $fee['base_amount'];
        }

        if ($totalBaseAmount <= $maxTotalFee) {
            return $fees;
        }

        // Scale all fees proportionally to fit within the cap
        $ratio = $maxTotalFee / $totalBaseAmount;

        $this->debugLog(
            sprintf(
                'Global max fee cap %.4f applied (total was %.4f), scaling by ratio %.4f',
                $maxTotalFee,
                $totalBaseAmount,
                $ratio
            ),
            $storeId
        );

        foreach ($fees as &$fee) {
            $fee['base_amount'] = round($fee['base_amount'] * $ratio, 4);
            $fee['amount'] = round($fee['amount'] * $ratio, 4);
            $fee['base_tax'] = round($fee['base_tax'] * $ratio, 4);
            $fee['tax'] = round($fee['tax'] * $ratio, 4);
        }
        unset($fee);

        return $fees;
    }

    /**
     * Get the subtotal to use for percentage calculations.
     *
     * @param Quote $quote
     * @param int $storeId
     * @return float
     */
    private function getSubtotal(Quote $quote, int $storeId): float
    {
        $shippingAddress = $quote->getShippingAddress();

        if ($this->helper->isApplyAfterDiscount($storeId)) {
            $subtotal = (float)$shippingAddress->getBaseSubtotalWithDiscount();
            if ($subtotal <= 0.0) {
                $subtotal = (float)$quote->getBaseSubtotalWithDiscount();
            }
        } else {
            $subtotal = (float)$shippingAddress->getBaseSubtotal();
            if ($subtotal <= 0.0) {
                $subtotal = (float)$quote->getBaseSubtotal();
            }
        }

        return max($subtotal, 0.0);
    }

    /**
     * Parse a comma-separated string into a trimmed array.
     *
     * @param string|null $value
     * @return array
     */
    private function parseCommaSeparated(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', array_filter(explode(',', $value), function ($v) {
            return trim($v) !== '';
        }));
    }

    /**
     * Log debug message if debug mode is enabled.
     *
     * @param string $message
     * @param int|null $storeId
     * @return void
     */
    private function debugLog(string $message, ?int $storeId = null): void
    {
        if ($this->helper->isDebugMode($storeId)) {
            $this->logger->debug('Panth_ExtraFee: ' . $message);
        }
    }
}
