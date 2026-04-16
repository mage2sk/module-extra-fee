<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;

class FeeRule extends AbstractModel implements FeeRuleInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_extra_fee_rule';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(FeeRuleResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setRuleId(int $ruleId): FeeRuleInterface
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): FeeRuleInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): FeeRuleInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    /**
     * @inheritdoc
     */
    public function getFeeType(): ?string
    {
        return $this->getData(self::FEE_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setFeeType(string $feeType): FeeRuleInterface
    {
        return $this->setData(self::FEE_TYPE, $feeType);
    }

    /**
     * @inheritdoc
     */
    public function getFeeAmount(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setFeeAmount(float $feeAmount): FeeRuleInterface
    {
        return $this->setData(self::FEE_AMOUNT, $feeAmount);
    }

    /**
     * @inheritdoc
     */
    public function getFeeAmountPercent(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT_PERCENT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setFeeAmountPercent(float $feeAmountPercent): FeeRuleInterface
    {
        return $this->setData(self::FEE_AMOUNT_PERCENT, $feeAmountPercent);
    }

    /**
     * @inheritdoc
     */
    public function getFeeLabel(): ?string
    {
        return $this->getData(self::FEE_LABEL);
    }

    /**
     * @inheritdoc
     */
    public function setFeeLabel(string $feeLabel): FeeRuleInterface
    {
        return $this->setData(self::FEE_LABEL, $feeLabel);
    }

    /**
     * @inheritdoc
     */
    public function getApplyPer(): ?string
    {
        return $this->getData(self::APPLY_PER);
    }

    /**
     * @inheritdoc
     */
    public function setApplyPer(string $applyPer): FeeRuleInterface
    {
        return $this->setData(self::APPLY_PER, $applyPer);
    }

    /**
     * @inheritdoc
     */
    public function getMinFeeAmount(): ?float
    {
        $value = $this->getData(self::MIN_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMinFeeAmount(float $minFeeAmount): FeeRuleInterface
    {
        return $this->setData(self::MIN_FEE_AMOUNT, $minFeeAmount);
    }

    /**
     * @inheritdoc
     */
    public function getMaxFeeAmount(): ?float
    {
        $value = $this->getData(self::MAX_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMaxFeeAmount(float $maxFeeAmount): FeeRuleInterface
    {
        return $this->setData(self::MAX_FEE_AMOUNT, $maxFeeAmount);
    }

    /**
     * @inheritdoc
     */
    public function getTaxClassId(): ?int
    {
        $value = $this->getData(self::TAX_CLASS_ID);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setTaxClassId(int $taxClassId): FeeRuleInterface
    {
        return $this->setData(self::TAX_CLASS_ID, $taxClassId);
    }

    /**
     * @inheritdoc
     */
    public function getIsRefundable(): bool
    {
        return (bool)$this->getData(self::IS_REFUNDABLE);
    }

    /**
     * @inheritdoc
     */
    public function setIsRefundable(bool $isRefundable): FeeRuleInterface
    {
        return $this->setData(self::IS_REFUNDABLE, $isRefundable);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentMethods(): ?string
    {
        return $this->getData(self::PAYMENT_METHODS);
    }

    /**
     * @inheritdoc
     */
    public function setPaymentMethods(string $paymentMethods): FeeRuleInterface
    {
        return $this->setData(self::PAYMENT_METHODS, $paymentMethods);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerGroups(): ?string
    {
        return $this->getData(self::CUSTOMER_GROUPS);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerGroups(string $customerGroups): FeeRuleInterface
    {
        return $this->setData(self::CUSTOMER_GROUPS, $customerGroups);
    }

    /**
     * @inheritdoc
     */
    public function getCountries(): ?string
    {
        return $this->getData(self::COUNTRIES);
    }

    /**
     * @inheritdoc
     */
    public function setCountries(string $countries): FeeRuleInterface
    {
        return $this->setData(self::COUNTRIES, $countries);
    }

    /**
     * @inheritdoc
     */
    public function getRegions(): ?string
    {
        return $this->getData(self::REGIONS);
    }

    /**
     * @inheritdoc
     */
    public function setRegions(string $regions): FeeRuleInterface
    {
        return $this->setData(self::REGIONS, $regions);
    }

    /**
     * @inheritdoc
     */
    public function getProductIds(): ?string
    {
        return $this->getData(self::PRODUCT_IDS);
    }

    /**
     * @inheritdoc
     */
    public function setProductIds(string $productIds): FeeRuleInterface
    {
        return $this->setData(self::PRODUCT_IDS, $productIds);
    }

    /**
     * @inheritdoc
     */
    public function getProductSkus(): ?string
    {
        return $this->getData(self::PRODUCT_SKUS);
    }

    /**
     * @inheritdoc
     */
    public function setProductSkus(string $productSkus): FeeRuleInterface
    {
        return $this->setData(self::PRODUCT_SKUS, $productSkus);
    }

    /**
     * @inheritdoc
     */
    public function getCategoryIds(): ?string
    {
        return $this->getData(self::CATEGORY_IDS);
    }

    /**
     * @inheritdoc
     */
    public function setCategoryIds(string $categoryIds): FeeRuleInterface
    {
        return $this->setData(self::CATEGORY_IDS, $categoryIds);
    }

    /**
     * @inheritdoc
     */
    public function getMinOrderSubtotal(): ?float
    {
        $value = $this->getData(self::MIN_ORDER_SUBTOTAL);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMinOrderSubtotal(float $minOrderSubtotal): FeeRuleInterface
    {
        return $this->setData(self::MIN_ORDER_SUBTOTAL, $minOrderSubtotal);
    }

    /**
     * @inheritdoc
     */
    public function getMaxOrderSubtotal(): ?float
    {
        $value = $this->getData(self::MAX_ORDER_SUBTOTAL);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMaxOrderSubtotal(float $maxOrderSubtotal): FeeRuleInterface
    {
        return $this->setData(self::MAX_ORDER_SUBTOTAL, $maxOrderSubtotal);
    }

    /**
     * @inheritdoc
     */
    public function getMinOrderQty(): ?int
    {
        $value = $this->getData(self::MIN_ORDER_QTY);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMinOrderQty(int $minOrderQty): FeeRuleInterface
    {
        return $this->setData(self::MIN_ORDER_QTY, $minOrderQty);
    }

    /**
     * @inheritdoc
     */
    public function getMaxOrderQty(): ?int
    {
        $value = $this->getData(self::MAX_ORDER_QTY);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMaxOrderQty(int $maxOrderQty): FeeRuleInterface
    {
        return $this->setData(self::MAX_ORDER_QTY, $maxOrderQty);
    }

    /**
     * @inheritdoc
     */
    public function getDateFrom(): ?string
    {
        return $this->getData(self::DATE_FROM);
    }

    /**
     * @inheritdoc
     */
    public function setDateFrom(?string $dateFrom): FeeRuleInterface
    {
        return $this->setData(self::DATE_FROM, $dateFrom);
    }

    /**
     * @inheritdoc
     */
    public function getDateTo(): ?string
    {
        return $this->getData(self::DATE_TO);
    }

    /**
     * @inheritdoc
     */
    public function setDateTo(?string $dateTo): FeeRuleInterface
    {
        return $this->setData(self::DATE_TO, $dateTo);
    }

    /**
     * @inheritdoc
     */
    public function getStoreIds(): ?string
    {
        return $this->getData(self::STORE_IDS);
    }

    /**
     * @inheritdoc
     */
    public function setStoreIds(string $storeIds): FeeRuleInterface
    {
        return $this->setData(self::STORE_IDS, $storeIds);
    }

    /**
     * @inheritdoc
     */
    public function getWebsiteIds(): ?string
    {
        return $this->getData(self::WEBSITE_IDS);
    }

    /**
     * @inheritdoc
     */
    public function setWebsiteIds(string $websiteIds): FeeRuleInterface
    {
        return $this->setData(self::WEBSITE_IDS, $websiteIds);
    }

    /**
     * @inheritdoc
     */
    public function getStopFurtherRules(): bool
    {
        return (bool)$this->getData(self::STOP_FURTHER_RULES);
    }

    /**
     * @inheritdoc
     */
    public function setStopFurtherRules(bool $stopFurtherRules): FeeRuleInterface
    {
        return $this->setData(self::STOP_FURTHER_RULES, $stopFurtherRules);
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder(): ?int
    {
        $value = $this->getData(self::SORT_ORDER);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritdoc
     */
    public function setSortOrder(int $sortOrder): FeeRuleInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritdoc
     */
    public function setDescription(?string $description): FeeRuleInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritdoc
     */
    public function getIncludeInSubtotal(): bool
    {
        return (bool)$this->getData(self::INCLUDE_IN_SUBTOTAL);
    }

    /**
     * @inheritdoc
     */
    public function setIncludeInSubtotal(bool $includeInSubtotal): FeeRuleInterface
    {
        return $this->setData(self::INCLUDE_IN_SUBTOTAL, $includeInSubtotal);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(string $createdAt): FeeRuleInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(string $updatedAt): FeeRuleInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * Get payment methods as array
     *
     * @return array
     */
    public function getPaymentMethodsArray(): array
    {
        $value = $this->getPaymentMethods();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get customer groups as array
     *
     * @return array
     */
    public function getCustomerGroupsArray(): array
    {
        $value = $this->getCustomerGroups();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get countries as array
     *
     * @return array
     */
    public function getCountriesArray(): array
    {
        $value = $this->getCountries();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get product IDs as array
     *
     * @return array
     */
    public function getProductIdsArray(): array
    {
        $value = $this->getProductIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get category IDs as array
     *
     * @return array
     */
    public function getCategoryIdsArray(): array
    {
        $value = $this->getCategoryIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get store IDs as array
     *
     * @return array
     */
    public function getStoreIdsArray(): array
    {
        $value = $this->getStoreIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    /**
     * Get website IDs as array
     *
     * @return array
     */
    public function getWebsiteIdsArray(): array
    {
        $value = $this->getWebsiteIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }
}
