<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\ExtraFee\Api\Data\FeeRuleInterface;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;

class FeeRule extends AbstractModel implements FeeRuleInterface
{
    protected $_eventPrefix = 'panth_extra_fee_rule';

    protected function _construct(): void
    {
        $this->_init(FeeRuleResource::class);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setRuleId(int $ruleId): FeeRuleInterface
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): FeeRuleInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): FeeRuleInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getFeeType(): ?string
    {
        return $this->getData(self::FEE_TYPE);
    }

    public function setFeeType(string $feeType): FeeRuleInterface
    {
        return $this->setData(self::FEE_TYPE, $feeType);
    }

    public function getFeeAmount(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeAmount(float $feeAmount): FeeRuleInterface
    {
        return $this->setData(self::FEE_AMOUNT, $feeAmount);
    }

    public function getFeeAmountPercent(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT_PERCENT);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeAmountPercent(float $feeAmountPercent): FeeRuleInterface
    {
        return $this->setData(self::FEE_AMOUNT_PERCENT, $feeAmountPercent);
    }

    public function getFeeLabel(): ?string
    {
        return $this->getData(self::FEE_LABEL);
    }

    public function setFeeLabel(string $feeLabel): FeeRuleInterface
    {
        return $this->setData(self::FEE_LABEL, $feeLabel);
    }

    public function getApplyPer(): ?string
    {
        return $this->getData(self::APPLY_PER);
    }

    public function setApplyPer(string $applyPer): FeeRuleInterface
    {
        return $this->setData(self::APPLY_PER, $applyPer);
    }

    public function getMinFeeAmount(): ?float
    {
        $value = $this->getData(self::MIN_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setMinFeeAmount(float $minFeeAmount): FeeRuleInterface
    {
        return $this->setData(self::MIN_FEE_AMOUNT, $minFeeAmount);
    }

    public function getMaxFeeAmount(): ?float
    {
        $value = $this->getData(self::MAX_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setMaxFeeAmount(float $maxFeeAmount): FeeRuleInterface
    {
        return $this->setData(self::MAX_FEE_AMOUNT, $maxFeeAmount);
    }

    public function getTaxClassId(): ?int
    {
        $value = $this->getData(self::TAX_CLASS_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setTaxClassId(int $taxClassId): FeeRuleInterface
    {
        return $this->setData(self::TAX_CLASS_ID, $taxClassId);
    }

    public function getIsRefundable(): bool
    {
        return (bool)$this->getData(self::IS_REFUNDABLE);
    }

    public function setIsRefundable(bool $isRefundable): FeeRuleInterface
    {
        return $this->setData(self::IS_REFUNDABLE, $isRefundable);
    }

    public function getPaymentMethods(): ?string
    {
        return $this->getData(self::PAYMENT_METHODS);
    }

    public function setPaymentMethods(string $paymentMethods): FeeRuleInterface
    {
        return $this->setData(self::PAYMENT_METHODS, $paymentMethods);
    }

    public function getCustomerGroups(): ?string
    {
        return $this->getData(self::CUSTOMER_GROUPS);
    }

    public function setCustomerGroups(string $customerGroups): FeeRuleInterface
    {
        return $this->setData(self::CUSTOMER_GROUPS, $customerGroups);
    }

    public function getCountries(): ?string
    {
        return $this->getData(self::COUNTRIES);
    }

    public function setCountries(string $countries): FeeRuleInterface
    {
        return $this->setData(self::COUNTRIES, $countries);
    }

    public function getRegions(): ?string
    {
        return $this->getData(self::REGIONS);
    }

    public function setRegions(string $regions): FeeRuleInterface
    {
        return $this->setData(self::REGIONS, $regions);
    }

    public function getProductIds(): ?string
    {
        return $this->getData(self::PRODUCT_IDS);
    }

    public function setProductIds(string $productIds): FeeRuleInterface
    {
        return $this->setData(self::PRODUCT_IDS, $productIds);
    }

    public function getProductSkus(): ?string
    {
        return $this->getData(self::PRODUCT_SKUS);
    }

    public function setProductSkus(string $productSkus): FeeRuleInterface
    {
        return $this->setData(self::PRODUCT_SKUS, $productSkus);
    }

    public function getCategoryIds(): ?string
    {
        return $this->getData(self::CATEGORY_IDS);
    }

    public function setCategoryIds(string $categoryIds): FeeRuleInterface
    {
        return $this->setData(self::CATEGORY_IDS, $categoryIds);
    }

    public function getMinOrderSubtotal(): ?float
    {
        $value = $this->getData(self::MIN_ORDER_SUBTOTAL);
        return $value !== null ? (float)$value : null;
    }

    public function setMinOrderSubtotal(float $minOrderSubtotal): FeeRuleInterface
    {
        return $this->setData(self::MIN_ORDER_SUBTOTAL, $minOrderSubtotal);
    }

    public function getMaxOrderSubtotal(): ?float
    {
        $value = $this->getData(self::MAX_ORDER_SUBTOTAL);
        return $value !== null ? (float)$value : null;
    }

    public function setMaxOrderSubtotal(float $maxOrderSubtotal): FeeRuleInterface
    {
        return $this->setData(self::MAX_ORDER_SUBTOTAL, $maxOrderSubtotal);
    }

    public function getMinOrderQty(): ?int
    {
        $value = $this->getData(self::MIN_ORDER_QTY);
        return $value !== null ? (int)$value : null;
    }

    public function setMinOrderQty(int $minOrderQty): FeeRuleInterface
    {
        return $this->setData(self::MIN_ORDER_QTY, $minOrderQty);
    }

    public function getMaxOrderQty(): ?int
    {
        $value = $this->getData(self::MAX_ORDER_QTY);
        return $value !== null ? (int)$value : null;
    }

    public function setMaxOrderQty(int $maxOrderQty): FeeRuleInterface
    {
        return $this->setData(self::MAX_ORDER_QTY, $maxOrderQty);
    }

    public function getDateFrom(): ?string
    {
        return $this->getData(self::DATE_FROM);
    }

    public function setDateFrom(?string $dateFrom): FeeRuleInterface
    {
        return $this->setData(self::DATE_FROM, $dateFrom);
    }

    public function getDateTo(): ?string
    {
        return $this->getData(self::DATE_TO);
    }

    public function setDateTo(?string $dateTo): FeeRuleInterface
    {
        return $this->setData(self::DATE_TO, $dateTo);
    }

    public function getStoreIds(): ?string
    {
        return $this->getData(self::STORE_IDS);
    }

    public function setStoreIds(string $storeIds): FeeRuleInterface
    {
        return $this->setData(self::STORE_IDS, $storeIds);
    }

    public function getWebsiteIds(): ?string
    {
        return $this->getData(self::WEBSITE_IDS);
    }

    public function setWebsiteIds(string $websiteIds): FeeRuleInterface
    {
        return $this->setData(self::WEBSITE_IDS, $websiteIds);
    }

    public function getStopFurtherRules(): bool
    {
        return (bool)$this->getData(self::STOP_FURTHER_RULES);
    }

    public function setStopFurtherRules(bool $stopFurtherRules): FeeRuleInterface
    {
        return $this->setData(self::STOP_FURTHER_RULES, $stopFurtherRules);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData(self::SORT_ORDER);
        return $value !== null ? (int)$value : null;
    }

    public function setSortOrder(int $sortOrder): FeeRuleInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription(?string $description): FeeRuleInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getIncludeInSubtotal(): bool
    {
        return (bool)$this->getData(self::INCLUDE_IN_SUBTOTAL);
    }

    public function setIncludeInSubtotal(bool $includeInSubtotal): FeeRuleInterface
    {
        return $this->setData(self::INCLUDE_IN_SUBTOTAL, $includeInSubtotal);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): FeeRuleInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): FeeRuleInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getPaymentMethodsArray(): array
    {
        $value = $this->getPaymentMethods();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getCustomerGroupsArray(): array
    {
        $value = $this->getCustomerGroups();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getCountriesArray(): array
    {
        $value = $this->getCountries();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getProductIdsArray(): array
    {
        $value = $this->getProductIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getCategoryIdsArray(): array
    {
        $value = $this->getCategoryIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getStoreIdsArray(): array
    {
        $value = $this->getStoreIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }

    public function getWebsiteIdsArray(): array
    {
        $value = $this->getWebsiteIds();
        return $value ? array_filter(explode(',', $value)) : [];
    }
}
