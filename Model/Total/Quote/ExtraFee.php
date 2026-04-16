<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Quote;

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
    private const TOTAL_CODE = 'panth_extra_fee';

    public function __construct(
        private readonly FeeCalculator $feeCalculator,
        private readonly QuoteFeeResource $quoteFeeResource,
        private readonly QuoteFeeFactory $quoteFeeFactory,
        private readonly QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger
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

        $storeId = (int)$quote->getStoreId();

        if (!$this->helper->shouldApplyFees($storeId)) {
            return $this;
        }

        try {
            $fees = $this->feeCalculator->calculateFees($quote);
            $quoteId = (int)$quote->getId();

            // Only clear and re-save if we have fees OR if quote has existing fees to remove
            if (!empty($fees) && $quoteId > 0) {
                $this->clearQuoteFees($quoteId);
                foreach ($fees as $fee) {
                    $this->saveQuoteFee($quote, $fee);
                }
            }

            $totalFeeAmount = 0.0;
            $baseTotalFeeAmount = 0.0;
            foreach ($fees as $fee) {
                $totalFeeAmount += $fee['amount'];
                $baseTotalFeeAmount += $fee['base_amount'];
            }

            // Add to grand total
            $total->addTotalAmount(self::TOTAL_CODE, $totalFeeAmount);
            $total->addBaseTotalAmount(self::TOTAL_CODE, $baseTotalFeeAmount);
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

        // Always read from database — works across different object instances
        $quoteId = (int)$quote->getId();
        if ($quoteId <= 0) {
            return [];
        }

        $collection = $this->quoteFeeCollectionFactory->create();
        $collection->addQuoteFilter($quoteId);

        if ($collection->getSize() === 0) {
            return [];
        }

        // Return each fee as its own segment row
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

    public function getLabel(): \Magento\Framework\Phrase
    {
        return __('Additional Fees');
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

    private function saveQuoteFee(Quote $quote, array $fee): void
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
            $this->quoteFeeResource->save($quoteFee);
        } catch (\Exception $e) {
            $this->logger->error('Panth_ExtraFee: Error saving quote fee: ' . $e->getMessage());
        }
    }
}
