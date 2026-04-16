<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Calculator;

use Magento\Quote\Model\Quote;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;
use Panth\ExtraFee\Model\FeeRule;

class ConditionChecker
{
    /**
     * Check if a fee rule is valid for the given quote.
     * All conditions must pass for the rule to be valid.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    public function isRuleValid(FeeRule $rule, Quote $quote): bool
    {
        if (!$rule->getIsActive()) {
            return false;
        }

        return $this->isStoreValid($rule, $quote)
            && $this->isWebsiteValid($rule, $quote)
            && $this->isDateValid($rule)
            && $this->isCustomerGroupValid($rule, $quote)
            && $this->isPaymentMethodValid($rule, $quote)
            && $this->isCountryValid($rule, $quote)
            && $this->isRegionValid($rule, $quote)
            && $this->isSubtotalValid($rule, $quote)
            && $this->isQuantityValid($rule, $quote)
            && $this->isProductValid($rule, $quote)
            && $this->isCategoryValid($rule, $quote);
    }

    /**
     * Validate store condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isStoreValid(FeeRule $rule, Quote $quote): bool
    {
        $storeIds = $rule->getStoreIdsArray();

        if (empty($storeIds) || in_array('0', $storeIds, true)) {
            return true;
        }

        return in_array((string)$quote->getStoreId(), $storeIds, true);
    }

    /**
     * Validate website condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isWebsiteValid(FeeRule $rule, Quote $quote): bool
    {
        $websiteIds = $rule->getWebsiteIdsArray();

        if (empty($websiteIds)) {
            return true;
        }

        $store = $quote->getStore();
        $quoteWebsiteId = $store ? (string)$store->getWebsiteId() : '0';

        return in_array($quoteWebsiteId, $websiteIds, true);
    }

    /**
     * Validate date range condition.
     *
     * @param FeeRule $rule
     * @return bool
     */
    private function isDateValid(FeeRule $rule): bool
    {
        $now = date('Y-m-d');

        $dateFrom = $rule->getDateFrom();
        if ($dateFrom !== null && $dateFrom !== '' && $now < $dateFrom) {
            return false;
        }

        $dateTo = $rule->getDateTo();
        if ($dateTo !== null && $dateTo !== '' && $now > $dateTo) {
            return false;
        }

        return true;
    }

    /**
     * Validate customer group condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isCustomerGroupValid(FeeRule $rule, Quote $quote): bool
    {
        $customerGroups = $rule->getCustomerGroupsArray();

        if (empty($customerGroups)) {
            return true;
        }

        $quoteGroupId = (string)$quote->getCustomerGroupId();

        return in_array($quoteGroupId, $customerGroups, true);
    }

    /**
     * Validate payment method condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isPaymentMethodValid(FeeRule $rule, Quote $quote): bool
    {
        $paymentMethods = $rule->getPaymentMethodsArray();

        if (empty($paymentMethods)) {
            return true;
        }

        $payment = $quote->getPayment();
        $quotePaymentMethod = $payment ? (string)$payment->getMethod() : '';

        if ($quotePaymentMethod === '') {
            // Payment method not yet selected — rule requires specific methods, so skip
            return false;
        }

        return in_array($quotePaymentMethod, $paymentMethods, true);
    }

    /**
     * Validate country condition against billing or shipping address.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isCountryValid(FeeRule $rule, Quote $quote): bool
    {
        $countries = $rule->getCountriesArray();

        if (empty($countries)) {
            return true;
        }

        $shippingCountry = $quote->getShippingAddress()
            ? (string)$quote->getShippingAddress()->getCountryId()
            : '';
        $billingCountry = $quote->getBillingAddress()
            ? (string)$quote->getBillingAddress()->getCountryId()
            : '';

        if ($shippingCountry !== '' && in_array($shippingCountry, $countries, true)) {
            return true;
        }

        if ($billingCountry !== '' && in_array($billingCountry, $countries, true)) {
            return true;
        }

        return false;
    }

    /**
     * Validate region condition against billing or shipping address.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isRegionValid(FeeRule $rule, Quote $quote): bool
    {
        $regions = $this->parseCommaSeparated($rule->getRegions());

        if (empty($regions)) {
            return true;
        }

        $shippingRegionId = $quote->getShippingAddress()
            ? (string)$quote->getShippingAddress()->getRegionId()
            : '';
        $billingRegionId = $quote->getBillingAddress()
            ? (string)$quote->getBillingAddress()->getRegionId()
            : '';

        if ($shippingRegionId !== '' && in_array($shippingRegionId, $regions, true)) {
            return true;
        }

        if ($billingRegionId !== '' && in_array($billingRegionId, $regions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Validate subtotal condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isSubtotalValid(FeeRule $rule, Quote $quote): bool
    {
        $subtotal = (float)$quote->getBaseSubtotal();

        $minSubtotal = $rule->getMinOrderSubtotal();
        if ($minSubtotal !== null && $minSubtotal > 0 && $subtotal < $minSubtotal) {
            return false;
        }

        $maxSubtotal = $rule->getMaxOrderSubtotal();
        if ($maxSubtotal !== null && $maxSubtotal > 0 && $subtotal > $maxSubtotal) {
            return false;
        }

        return true;
    }

    /**
     * Validate quantity condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isQuantityValid(FeeRule $rule, Quote $quote): bool
    {
        $totalQty = (float)$quote->getItemsQty();

        $minQty = $rule->getMinOrderQty();
        if ($minQty !== null && $minQty > 0 && $totalQty < $minQty) {
            return false;
        }

        $maxQty = $rule->getMaxOrderQty();
        if ($maxQty !== null && $maxQty > 0 && $totalQty > $maxQty) {
            return false;
        }

        return true;
    }

    /**
     * Validate product condition (product IDs or SKUs).
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isProductValid(FeeRule $rule, Quote $quote): bool
    {
        $productIds = $rule->getProductIdsArray();
        $productSkus = $this->parseCommaSeparated($rule->getProductSkus());

        if (empty($productIds) && empty($productSkus)) {
            return true;
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            if (!empty($productIds) && in_array((string)$item->getProductId(), $productIds, true)) {
                return true;
            }

            if (!empty($productSkus) && in_array($item->getSku(), $productSkus, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate category condition.
     *
     * @param FeeRule $rule
     * @param Quote $quote
     * @return bool
     */
    private function isCategoryValid(FeeRule $rule, Quote $quote): bool
    {
        $categoryIds = $rule->getCategoryIdsArray();

        if (empty($categoryIds)) {
            return true;
        }

        foreach ($quote->getAllVisibleItems() as $item) {
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
}
