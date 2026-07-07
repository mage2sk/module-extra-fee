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
    private OrderFeeResource $orderFeeResource;

    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    private LoggerInterface $logger;

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

            foreach ($collection as $orderFee) {
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

                $baseRemainingFee = $baseFeeInvoiced - $baseFeeRefunded;
                $remainingFee = $feeInvoiced - $feeRefunded;

                if ($baseRemainingFee <= 0.0) {
                    continue;
                }

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

    private function isRuleRefundable(OrderFee $orderFee): bool
    {
        $isRefundable = $orderFee->getData('is_refundable');
        if ($isRefundable !== null) {
            return (bool)$isRefundable;
        }

        return true;
    }
}
