<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface FeeRuleInterface extends ExtensibleDataInterface
{
    const RULE_ID = 'rule_id';
    const NAME = 'name';
    const IS_ACTIVE = 'is_active';
    const FEE_TYPE = 'fee_type';
    const FEE_AMOUNT = 'fee_amount';
    const FEE_AMOUNT_PERCENT = 'fee_amount_percent';
    const FEE_LABEL = 'fee_label';
    const APPLY_PER = 'apply_per';
    const MIN_FEE_AMOUNT = 'min_fee_amount';
    const MAX_FEE_AMOUNT = 'max_fee_amount';
    const TAX_CLASS_ID = 'tax_class_id';
    const IS_REFUNDABLE = 'is_refundable';
    const PAYMENT_METHODS = 'payment_methods';
    const CUSTOMER_GROUPS = 'customer_groups';
    const COUNTRIES = 'countries';
    const REGIONS = 'regions';
    const PRODUCT_IDS = 'product_ids';
    const PRODUCT_SKUS = 'product_skus';
    const CATEGORY_IDS = 'category_ids';
    const MIN_ORDER_SUBTOTAL = 'min_order_subtotal';
    const MAX_ORDER_SUBTOTAL = 'max_order_subtotal';
    const MIN_ORDER_QTY = 'min_order_qty';
    const MAX_ORDER_QTY = 'max_order_qty';
    const DATE_FROM = 'date_from';
    const DATE_TO = 'date_to';
    const STORE_IDS = 'store_ids';
    const WEBSITE_IDS = 'website_ids';
    const STOP_FURTHER_RULES = 'stop_further_rules';
    const SORT_ORDER = 'sort_order';
    const DESCRIPTION = 'description';
    const INCLUDE_IN_SUBTOTAL = 'include_in_subtotal';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getRuleId(): ?int;

    /**
     * @param int $ruleId
     * @return $this
     */
    public function setRuleId(int $ruleId): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self;

    /**
     * @return string|null
     */
    public function getFeeType(): ?string;

    /**
     * @param string $feeType
     * @return $this
     */
    public function setFeeType(string $feeType): self;

    /**
     * @return float|null
     */
    public function getFeeAmount(): ?float;

    /**
     * @param float $feeAmount
     * @return $this
     */
    public function setFeeAmount(float $feeAmount): self;

    /**
     * @return float|null
     */
    public function getFeeAmountPercent(): ?float;

    /**
     * @param float $feeAmountPercent
     * @return $this
     */
    public function setFeeAmountPercent(float $feeAmountPercent): self;

    /**
     * @return string|null
     */
    public function getFeeLabel(): ?string;

    /**
     * @param string $feeLabel
     * @return $this
     */
    public function setFeeLabel(string $feeLabel): self;

    /**
     * @return string|null
     */
    public function getApplyPer(): ?string;

    /**
     * @param string $applyPer
     * @return $this
     */
    public function setApplyPer(string $applyPer): self;

    /**
     * @return float|null
     */
    public function getMinFeeAmount(): ?float;

    /**
     * @param float $minFeeAmount
     * @return $this
     */
    public function setMinFeeAmount(float $minFeeAmount): self;

    /**
     * @return float|null
     */
    public function getMaxFeeAmount(): ?float;

    /**
     * @param float $maxFeeAmount
     * @return $this
     */
    public function setMaxFeeAmount(float $maxFeeAmount): self;

    /**
     * @return int|null
     */
    public function getTaxClassId(): ?int;

    /**
     * @param int $taxClassId
     * @return $this
     */
    public function setTaxClassId(int $taxClassId): self;

    /**
     * @return bool
     */
    public function getIsRefundable(): bool;

    /**
     * @param bool $isRefundable
     * @return $this
     */
    public function setIsRefundable(bool $isRefundable): self;

    /**
     * @return string|null
     */
    public function getPaymentMethods(): ?string;

    /**
     * @param string $paymentMethods
     * @return $this
     */
    public function setPaymentMethods(string $paymentMethods): self;

    /**
     * @return string|null
     */
    public function getCustomerGroups(): ?string;

    /**
     * @param string $customerGroups
     * @return $this
     */
    public function setCustomerGroups(string $customerGroups): self;

    /**
     * @return string|null
     */
    public function getCountries(): ?string;

    /**
     * @param string $countries
     * @return $this
     */
    public function setCountries(string $countries): self;

    /**
     * @return string|null
     */
    public function getRegions(): ?string;

    /**
     * @param string $regions
     * @return $this
     */
    public function setRegions(string $regions): self;

    /**
     * @return string|null
     */
    public function getProductIds(): ?string;

    /**
     * @param string $productIds
     * @return $this
     */
    public function setProductIds(string $productIds): self;

    /**
     * @return string|null
     */
    public function getProductSkus(): ?string;

    /**
     * @param string $productSkus
     * @return $this
     */
    public function setProductSkus(string $productSkus): self;

    /**
     * @return string|null
     */
    public function getCategoryIds(): ?string;

    /**
     * @param string $categoryIds
     * @return $this
     */
    public function setCategoryIds(string $categoryIds): self;

    /**
     * @return float|null
     */
    public function getMinOrderSubtotal(): ?float;

    /**
     * @param float $minOrderSubtotal
     * @return $this
     */
    public function setMinOrderSubtotal(float $minOrderSubtotal): self;

    /**
     * @return float|null
     */
    public function getMaxOrderSubtotal(): ?float;

    /**
     * @param float $maxOrderSubtotal
     * @return $this
     */
    public function setMaxOrderSubtotal(float $maxOrderSubtotal): self;

    /**
     * @return int|null
     */
    public function getMinOrderQty(): ?int;

    /**
     * @param int $minOrderQty
     * @return $this
     */
    public function setMinOrderQty(int $minOrderQty): self;

    /**
     * @return int|null
     */
    public function getMaxOrderQty(): ?int;

    /**
     * @param int $maxOrderQty
     * @return $this
     */
    public function setMaxOrderQty(int $maxOrderQty): self;

    /**
     * @return string|null
     */
    public function getDateFrom(): ?string;

    /**
     * @param string|null $dateFrom
     * @return $this
     */
    public function setDateFrom(?string $dateFrom): self;

    /**
     * @return string|null
     */
    public function getDateTo(): ?string;

    /**
     * @param string|null $dateTo
     * @return $this
     */
    public function setDateTo(?string $dateTo): self;

    /**
     * @return string|null
     */
    public function getStoreIds(): ?string;

    /**
     * @param string $storeIds
     * @return $this
     */
    public function setStoreIds(string $storeIds): self;

    /**
     * @return string|null
     */
    public function getWebsiteIds(): ?string;

    /**
     * @param string $websiteIds
     * @return $this
     */
    public function setWebsiteIds(string $websiteIds): self;

    /**
     * @return bool
     */
    public function getStopFurtherRules(): bool;

    /**
     * @param bool $stopFurtherRules
     * @return $this
     */
    public function setStopFurtherRules(bool $stopFurtherRules): self;

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int;

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self;

    /**
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self;

    /**
     * @return bool
     */
    public function getIncludeInSubtotal(): bool;

    /**
     * @param bool $includeInSubtotal
     * @return $this
     */
    public function setIncludeInSubtotal(bool $includeInSubtotal): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
