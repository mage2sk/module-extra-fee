<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Psr\Log\LoggerInterface;

class ExtraFee extends AbstractTotal
{
    /**
     * @var OrderFeeResource
     */
    private OrderFeeResource $orderFeeResource;

    /**
     * @var OrderFeeCollectionFactory
     */
    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param OrderFeeResource $orderFeeResource
     * @param OrderFeeCollectionFactory $orderFeeCollectionFactory
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        OrderFeeResource $orderFeeResource,
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($data);
        $this->orderFeeResource = $orderFeeResource;
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Collect extra fee totals for credit memo.
     *
     * @param Creditmemo $creditmemo
     * @return $this
     */
    public function collect(Creditmemo $creditmemo): self
    {
        parent::collect($creditmemo);

        $order = $creditmemo->getOrder();
        $orderId = (int)$order->getId();

        if ($orderId <= 0) {
            return $this;
        }

        try {
            $collection = $this->orderFeeCollectionFactory->create();
            $collection->addFieldToFilter('order_id', $orderId);

            $totalFee = 0.0;
            $baseTotalFee = 0.0;
            $totalTax = 0.0;
            $baseTotalTax = 0.0;

            /** @var OrderFee $orderFee */
            foreach ($collection as $orderFee) {
                // Only refund fees that are marked as refundable
                // Check the linked rule's is_refundable flag via the order fee record
                // The fee_type 'small_order' is always refundable
                $feeType = (string)$orderFee->getFeeType();
                if ($feeType !== 'small_order') {
                    $ruleId = (int)$orderFee->getRuleId();
                    if ($ruleId > 0 && !$this->isRuleRefundable($orderFee)) {
                        continue;
                    }
                }

                $baseFeeInvoiced = (float)$orderFee->getBaseFeeInvoiced();
                $feeInvoiced = (float)$orderFee->getFeeInvoiced();
                $baseFeeRefunded = (float)$orderFee->getBaseFeeRefunded();
                $feeRefunded = (float)$orderFee->getFeeRefunded();
                $baseTaxAmount = (float)$orderFee->getBaseTaxAmount();
                $taxAmount = (float)$orderFee->getTaxAmount();
                $baseTaxRefunded = (float)$orderFee->getBaseTaxRefunded();
                $taxRefunded = (float)$orderFee->getTaxRefunded();

                // Calculate remaining refundable amount (can only refund what was invoiced)
                $baseRemainingFee = $baseFeeInvoiced - $baseFeeRefunded;
                $remainingFee = $feeInvoiced - $feeRefunded;

                if ($baseRemainingFee <= 0.0) {
                    continue;
                }

                // Calculate remaining tax to refund
                $baseRemainingTax = 0.0;
                $remainingTax = 0.0;
                if ($baseFeeInvoiced > 0.0) {
                    $baseFeeAmount = (float)$orderFee->getBaseFeeAmount();
                    if ($baseFeeAmount > 0.0) {
                        $ratio = $baseRemainingFee / $baseFeeAmount;
                        $baseRemainingTax = min(
                            round($baseTaxAmount * $ratio, 4),
                            $baseTaxAmount - $baseTaxRefunded
                        );
                        $remainingTax = min(
                            round($taxAmount * $ratio, 4),
                            $taxAmount - $taxRefunded
                        );
                    }
                }

                $baseTotalFee += $baseRemainingFee;
                $totalFee += $remainingFee;
                $baseTotalTax += max($baseRemainingTax, 0.0);
                $totalTax += max($remainingTax, 0.0);

                // Update refunded amounts on the OrderFee record
                $orderFee->setBaseFeeRefunded($baseFeeRefunded + $baseRemainingFee);
                $orderFee->setFeeRefunded($feeRefunded + $remainingFee);
                $orderFee->setBaseTaxRefunded($baseTaxRefunded + max($baseRemainingTax, 0.0));
                $orderFee->setTaxRefunded($taxRefunded + max($remainingTax, 0.0));
                $this->orderFeeResource->save($orderFee);
            }

            if ($baseTotalFee > 0.0 || $totalFee > 0.0) {
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalFee + $totalTax);
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalFee + $baseTotalTax);

                $creditmemo->setData('panth_extra_fee_amount', $totalFee);
                $creditmemo->setData('panth_base_extra_fee_amount', $baseTotalFee);
                $creditmemo->setData('panth_extra_fee_tax', $totalTax);
                $creditmemo->setData('panth_base_extra_fee_tax', $baseTotalTax);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Panth_ExtraFee: Error collecting creditmemo totals: %s', $e->getMessage())
            );
        }

        return $this;
    }

    /**
     * Check if the fee's associated rule is refundable.
     *
     * The OrderFee record does not store is_refundable directly,
     * so we look at the fee_type field. Rules that generate fees
     * store is_refundable in the rule table. For credit memos we
     * default to refundable (true) unless the order fee record
     * explicitly has an is_refundable data field set to false.
     *
     * @param OrderFee $orderFee
     * @return bool
     */
    private function isRuleRefundable(OrderFee $orderFee): bool
    {
        // If the OrderFee has an is_refundable data attribute, use it
        $isRefundable = $orderFee->getData('is_refundable');
        if ($isRefundable !== null) {
            return (bool)$isRefundable;
        }

        // Default to refundable
        return true;
    }
}
