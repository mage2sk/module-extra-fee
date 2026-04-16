<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;

class QuoteFee extends AbstractModel
{
    const ENTITY_ID = 'entity_id';
    const QUOTE_ID = 'quote_id';
    const RULE_ID = 'rule_id';
    const FEE_LABEL = 'fee_label';
    const FEE_TYPE = 'fee_type';
    const BASE_FEE_AMOUNT = 'base_fee_amount';
    const FEE_AMOUNT = 'fee_amount';
    const BASE_TAX_AMOUNT = 'base_tax_amount';
    const TAX_AMOUNT = 'tax_amount';
    const CREATED_AT = 'created_at';

    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_extra_fee_quote';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(QuoteFeeResource::class);
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @return int|null
     */
    public function getQuoteId(): ?int
    {
        $value = $this->getData(self::QUOTE_ID);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @return int|null
     */
    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $ruleId
     * @return $this
     */
    public function setRuleId(int $ruleId): self
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    /**
     * @return string|null
     */
    public function getFeeLabel(): ?string
    {
        return $this->getData(self::FEE_LABEL);
    }

    /**
     * @param string $feeLabel
     * @return $this
     */
    public function setFeeLabel(string $feeLabel): self
    {
        return $this->setData(self::FEE_LABEL, $feeLabel);
    }

    /**
     * @return string|null
     */
    public function getFeeType(): ?string
    {
        return $this->getData(self::FEE_TYPE);
    }

    /**
     * @param string $feeType
     * @return $this
     */
    public function setFeeType(string $feeType): self
    {
        return $this->setData(self::FEE_TYPE, $feeType);
    }

    /**
     * @return float|null
     */
    public function getBaseFeeAmount(): ?float
    {
        $value = $this->getData(self::BASE_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @param float $baseFeeAmount
     * @return $this
     */
    public function setBaseFeeAmount(float $baseFeeAmount): self
    {
        return $this->setData(self::BASE_FEE_AMOUNT, $baseFeeAmount);
    }

    /**
     * @return float|null
     */
    public function getFeeAmount(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @param float $feeAmount
     * @return $this
     */
    public function setFeeAmount(float $feeAmount): self
    {
        return $this->setData(self::FEE_AMOUNT, $feeAmount);
    }

    /**
     * @return float|null
     */
    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData(self::BASE_TAX_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @param float $baseTaxAmount
     * @return $this
     */
    public function setBaseTaxAmount(float $baseTaxAmount): self
    {
        return $this->setData(self::BASE_TAX_AMOUNT, $baseTaxAmount);
    }

    /**
     * @return float|null
     */
    public function getTaxAmount(): ?float
    {
        $value = $this->getData(self::TAX_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @param float $taxAmount
     * @return $this
     */
    public function setTaxAmount(float $taxAmount): self
    {
        return $this->setData(self::TAX_AMOUNT, $taxAmount);
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
