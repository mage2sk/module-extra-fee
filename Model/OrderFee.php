<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;

class OrderFee extends AbstractModel
{
    const ENTITY_ID = 'entity_id';
    const ORDER_ID = 'order_id';
    const QUOTE_ID = 'quote_id';
    const RULE_ID = 'rule_id';
    const FEE_LABEL = 'fee_label';
    const FEE_TYPE = 'fee_type';
    const BASE_FEE_AMOUNT = 'base_fee_amount';
    const FEE_AMOUNT = 'fee_amount';
    const BASE_TAX_AMOUNT = 'base_tax_amount';
    const TAX_AMOUNT = 'tax_amount';
    const BASE_FEE_AMOUNT_INCL_TAX = 'base_fee_amount_incl_tax';
    const FEE_AMOUNT_INCL_TAX = 'fee_amount_incl_tax';
    const BASE_FEE_REFUNDED = 'base_fee_refunded';
    const FEE_REFUNDED = 'fee_refunded';
    const BASE_TAX_REFUNDED = 'base_tax_refunded';
    const TAX_REFUNDED = 'tax_refunded';
    const BASE_FEE_INVOICED = 'base_fee_invoiced';
    const FEE_INVOICED = 'fee_invoiced';
    const CREATED_AT = 'created_at';

    protected $_eventPrefix = 'panth_extra_fee_order';

    protected function _construct(): void
    {
        $this->_init(OrderFeeResource::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData(self::ORDER_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData(self::QUOTE_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData(self::RULE_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setRuleId(int $ruleId): self
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    public function getFeeLabel(): ?string
    {
        return $this->getData(self::FEE_LABEL);
    }

    public function setFeeLabel(string $feeLabel): self
    {
        return $this->setData(self::FEE_LABEL, $feeLabel);
    }

    public function getFeeType(): ?string
    {
        return $this->getData(self::FEE_TYPE);
    }

    public function setFeeType(string $feeType): self
    {
        return $this->setData(self::FEE_TYPE, $feeType);
    }

    public function getBaseFeeAmount(): ?float
    {
        $value = $this->getData(self::BASE_FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseFeeAmount(float $baseFeeAmount): self
    {
        return $this->setData(self::BASE_FEE_AMOUNT, $baseFeeAmount);
    }

    public function getFeeAmount(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeAmount(float $feeAmount): self
    {
        return $this->setData(self::FEE_AMOUNT, $feeAmount);
    }

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData(self::BASE_TAX_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseTaxAmount(float $baseTaxAmount): self
    {
        return $this->setData(self::BASE_TAX_AMOUNT, $baseTaxAmount);
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData(self::TAX_AMOUNT);
        return $value !== null ? (float)$value : null;
    }

    public function setTaxAmount(float $taxAmount): self
    {
        return $this->setData(self::TAX_AMOUNT, $taxAmount);
    }

    public function getBaseFeeAmountInclTax(): ?float
    {
        $value = $this->getData(self::BASE_FEE_AMOUNT_INCL_TAX);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseFeeAmountInclTax(float $baseFeeAmountInclTax): self
    {
        return $this->setData(self::BASE_FEE_AMOUNT_INCL_TAX, $baseFeeAmountInclTax);
    }

    public function getFeeAmountInclTax(): ?float
    {
        $value = $this->getData(self::FEE_AMOUNT_INCL_TAX);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeAmountInclTax(float $feeAmountInclTax): self
    {
        return $this->setData(self::FEE_AMOUNT_INCL_TAX, $feeAmountInclTax);
    }

    public function getBaseFeeRefunded(): ?float
    {
        $value = $this->getData(self::BASE_FEE_REFUNDED);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseFeeRefunded(float $baseFeeRefunded): self
    {
        return $this->setData(self::BASE_FEE_REFUNDED, $baseFeeRefunded);
    }

    public function getFeeRefunded(): ?float
    {
        $value = $this->getData(self::FEE_REFUNDED);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeRefunded(float $feeRefunded): self
    {
        return $this->setData(self::FEE_REFUNDED, $feeRefunded);
    }

    public function getBaseTaxRefunded(): ?float
    {
        $value = $this->getData(self::BASE_TAX_REFUNDED);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseTaxRefunded(float $baseTaxRefunded): self
    {
        return $this->setData(self::BASE_TAX_REFUNDED, $baseTaxRefunded);
    }

    public function getTaxRefunded(): ?float
    {
        $value = $this->getData(self::TAX_REFUNDED);
        return $value !== null ? (float)$value : null;
    }

    public function setTaxRefunded(float $taxRefunded): self
    {
        return $this->setData(self::TAX_REFUNDED, $taxRefunded);
    }

    public function getBaseFeeInvoiced(): ?float
    {
        $value = $this->getData(self::BASE_FEE_INVOICED);
        return $value !== null ? (float)$value : null;
    }

    public function setBaseFeeInvoiced(float $baseFeeInvoiced): self
    {
        return $this->setData(self::BASE_FEE_INVOICED, $baseFeeInvoiced);
    }

    public function getFeeInvoiced(): ?float
    {
        $value = $this->getData(self::FEE_INVOICED);
        return $value !== null ? (float)$value : null;
    }

    public function setFeeInvoiced(float $feeInvoiced): self
    {
        return $this->setData(self::FEE_INVOICED, $feeInvoiced);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
