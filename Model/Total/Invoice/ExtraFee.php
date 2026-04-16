<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
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
     * Collect extra fee totals for invoice.
     *
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice): self
    {
        parent::collect($invoice);

        $order = $invoice->getOrder();
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
                $baseFeeAmount = (float)$orderFee->getBaseFeeAmount();
                $feeAmount = (float)$orderFee->getFeeAmount();
                $baseFeeInvoiced = (float)$orderFee->getBaseFeeInvoiced();
                $feeInvoiced = (float)$orderFee->getFeeInvoiced();
                $baseTaxAmount = (float)$orderFee->getBaseTaxAmount();
                $taxAmount = (float)$orderFee->getTaxAmount();

                // Calculate remaining amount to invoice
                $baseRemainingFee = $baseFeeAmount - $baseFeeInvoiced;
                $remainingFee = $feeAmount - $feeInvoiced;

                if ($baseRemainingFee <= 0.0) {
                    continue;
                }

                // Calculate tax proportion for the remaining fee
                $baseRemainingTax = 0.0;
                $remainingTax = 0.0;
                if ($baseFeeAmount > 0.0) {
                    $ratio = $baseRemainingFee / $baseFeeAmount;
                    $baseRemainingTax = round($baseTaxAmount * $ratio, 4);
                    $remainingTax = round($taxAmount * $ratio, 4);
                }

                $baseTotalFee += $baseRemainingFee;
                $totalFee += $remainingFee;
                $baseTotalTax += $baseRemainingTax;
                $totalTax += $remainingTax;

                // Update invoiced amounts on the OrderFee record
                $orderFee->setBaseFeeInvoiced($baseFeeInvoiced + $baseRemainingFee);
                $orderFee->setFeeInvoiced($feeInvoiced + $remainingFee);
                $this->orderFeeResource->save($orderFee);
            }

            if ($baseTotalFee > 0.0 || $totalFee > 0.0) {
                $invoice->setGrandTotal($invoice->getGrandTotal() + $totalFee + $totalTax);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalFee + $baseTotalTax);

                $invoice->setData('panth_extra_fee_amount', $totalFee);
                $invoice->setData('panth_base_extra_fee_amount', $baseTotalFee);
                $invoice->setData('panth_extra_fee_tax', $totalTax);
                $invoice->setData('panth_base_extra_fee_tax', $baseTotalTax);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Panth_ExtraFee: Error collecting invoice totals: %s', $e->getMessage())
            );
        }

        return $this;
    }
}
