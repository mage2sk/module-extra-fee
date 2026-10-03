<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Quote;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\Calculator\FeeCalculator;
use Panth\ExtraFee\Model\QuoteFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;
use Psr\Log\LoggerInterface;

class ExtraFee extends AbstractTotal
{
    public const CARRIER_FLAG = 'panth_extra_fee_carrier';

    public const PENDING_FEES_KEY = 'panth_extra_fee_pending';

    private const TOTAL_CODE = 'panth_extra_fee';

    public function __construct(
        private readonly FeeCalculator $feeCalculator,
        private readonly QuoteFeeResource $quoteFeeResource,
        private readonly QuoteFeeFactory $quoteFeeFactory,
        private readonly QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger,
        private readonly Json $json
    ) {
        $this->setCode(self::TOTAL_CODE);
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): self {
        parent::collect($quote, $shippingAssignment, $total);

        if (empty($shippingAssignment->getItems())) {
            return $this;
        }

        $address = $shippingAssignment->getShipping()->getAddress();
        $isCarrier = $this->isFeeCarrier($quote, $address);
        if ($address) {
            $address->setData(self::CARRIER_FLAG, $isCarrier);
        }
        if (!$isCarrier) {
            return $this;
        }

        $storeId = (int)$quote->getStoreId();
        $quoteId = (int)$quote->getId();

        if ($quoteId > 0) {
            $this->clearQuoteFees($quoteId);
        }

        if (!$this->helper->shouldApplyFees($storeId)) {
            return $this;
        }

        try {
            $fees = $this->feeCalculator->calculateFees($quote);
            $chargeTax = $this->helper->isChargeFeeTax($storeId);

            if ($quoteId > 0) {
                $quote->unsetData(self::PENDING_FEES_KEY);
                foreach ($fees as $fee) {
                    $this->saveQuoteFee($quote, $fee, $chargeTax);
                }
            } else {
                $quote->setData(self::PENDING_FEES_KEY, ['fees' => $fees, 'charge_tax' => $chargeTax]);
            }

            $totalFeeAmount = 0.0;
            $baseTotalFeeAmount = 0.0;
            $totalTax = 0.0;
            $baseTotalTax = 0.0;
            foreach ($fees as $fee) {
                $totalFeeAmount += $fee['amount'];
                $baseTotalFeeAmount += $fee['base_amount'];
                $totalTax += $fee['tax'];
                $baseTotalTax += $fee['base_tax'];
            }

            $total->addTotalAmount(self::TOTAL_CODE, $totalFeeAmount);
            $total->addBaseTotalAmount(self::TOTAL_CODE, $baseTotalFeeAmount);

            if ($chargeTax && ($totalTax > 0.0 || $baseTotalTax > 0.0)) {
                $total->addTotalAmount('tax', $totalTax);
                $total->addBaseTotalAmount('tax', $baseTotalTax);
            }
        } catch (\Exception $e) {
            $this->logger->error('Panth_ExtraFee: ' . $e->getMessage());
        }

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $storeId = (int)$quote->getStoreId();

        if (!$this->helper->shouldApplyFees($storeId)) {
            return [];
        }

        $quoteId = (int)$quote->getId();
        if ($quoteId <= 0) {
            return [];
        }

        $collection = $this->quoteFeeCollectionFactory->create();
        $collection->addQuoteFilter($quoteId);

        if ($collection->getSize() === 0) {
            return [];
        }

        $segments = [];
        foreach ($collection as $quoteFee) {
            $amount = (float)$quoteFee->getFeeAmount();
            if ($amount <= 0.0) {
                continue;
            }
            $segments[] = [
                'code'  => $this->getCode() . '_' . $quoteFee->getRuleId(),
                'title' => __($quoteFee->getFeeLabel()),
                'value' => $amount,
            ];
        }

        return $segments;
    }

    public function persistPendingFees(Quote $quote): void
    {
        $pending = $quote->getData(self::PENDING_FEES_KEY);
        $quote->unsetData(self::PENDING_FEES_KEY);
        $quoteId = (int)$quote->getId();
        if (!is_array($pending) || $quoteId <= 0) {
            return;
        }

        $this->clearQuoteFees($quoteId);
        foreach ((array)($pending['fees'] ?? []) as $fee) {
            $this->saveQuoteFee($quote, $fee, (bool)($pending['charge_tax'] ?? false));
        }
    }

    public function getLabel(): \Magento\Framework\Phrase
    {
        return __('Additional Fees');
    }

    private function isFeeCarrier(Quote $quote, $address): bool
    {
        if (!$quote->getIsMultiShipping() || !$address) {
            return true;
        }

        foreach ($quote->getAllShippingAddresses() as $candidate) {
            if (count($candidate->getAllItems()) === 0) {
                continue;
            }
            if ($candidate === $address) {
                return true;
            }
            return $candidate->getId() && (int)$candidate->getId() === (int)$address->getId();
        }

        return $address->getAddressType() === Quote\Address::ADDRESS_TYPE_BILLING;
    }

    private function clearQuoteFees(int $quoteId): void
    {
        if ($quoteId <= 0) {
            return;
        }

        try {
            $conn = $this->quoteFeeResource->getConnection();
            $conn->delete(
                $this->quoteFeeResource->getMainTable(),
                ['quote_id = ?' => $quoteId]
            );
        } catch (\Exception $e) {
            $this->logger->error('Panth_ExtraFee: Error clearing quote fees: ' . $e->getMessage());
        }
    }

    private function saveQuoteFee(Quote $quote, array $fee, bool $chargeTax): void
    {
        $quoteId = (int)$quote->getId();
        if ($quoteId <= 0) {
            return;
        }

        try {
            $quoteFee = $this->quoteFeeFactory->create();
            $quoteFee->setQuoteId($quoteId);
            $quoteFee->setRuleId((int)$fee['rule_id']);
            $quoteFee->setFeeLabel((string)$fee['label']);
            $quoteFee->setFeeType((string)$fee['fee_type']);
            $quoteFee->setBaseFeeAmount((float)$fee['base_amount']);
            $quoteFee->setFeeAmount((float)$fee['amount']);
            $quoteFee->setBaseTaxAmount((float)$fee['base_tax']);
            $quoteFee->setTaxAmount((float)$fee['tax']);
            $quoteFee->setData('tax_charged', $chargeTax ? 1 : 0);
            $quoteFee->setData(
                'item_breakdown',
                !empty($fee['items']) ? $this->json->serialize($fee['items']) : null
            );
            $this->quoteFeeResource->save($quoteFee);
        } catch (\Exception $e) {
            $this->logger->error('Panth_ExtraFee: Error saving quote fee: ' . $e->getMessage());
        }
    }
}
