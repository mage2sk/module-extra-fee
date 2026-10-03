<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Invoice;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Psr\Log\LoggerInterface;

class ExtraFee extends AbstractTotal
{
    public const ALLOCATIONS_KEY = 'panth_extra_fee_allocations';

    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    private LoggerInterface $logger;

    private Json $json;

    public function __construct(
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        LoggerInterface $logger,
        Json $json,
        array $data = []
    ) {
        parent::__construct($data);
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->logger = $logger;
        $this->json = $json;
    }

    public function collect(Invoice $invoice): self
    {
        parent::collect($invoice);

        $invoice->setData(self::ALLOCATIONS_KEY, []);

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
            $chargedTax = 0.0;
            $baseChargedTax = 0.0;
            $allocations = [];
            $details = [];

            foreach ($collection as $orderFee) {
                $baseFeeAmount = (float)$orderFee->getBaseFeeAmount();
                $feeAmount = (float)$orderFee->getFeeAmount();
                $baseFeeInvoiced = (float)$orderFee->getBaseFeeInvoiced();
                $feeInvoiced = (float)$orderFee->getFeeInvoiced();
                $baseTaxAmount = (float)$orderFee->getBaseTaxAmount();
                $taxAmount = (float)$orderFee->getTaxAmount();

                $baseRemainingFee = $baseFeeAmount - $baseFeeInvoiced;
                $remainingFee = $feeAmount - $feeInvoiced;

                if ($baseRemainingFee <= 0.0) {
                    continue;
                }

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
                if ((int)$orderFee->getData('tax_charged') === 1) {
                    $chargedTax += $remainingTax;
                    $baseChargedTax += $baseRemainingTax;
                }

                $allocations[(int)$orderFee->getId()] = [
                    'base_fee' => $baseRemainingFee,
                    'fee' => $remainingFee,
                    'base_tax' => $baseRemainingTax,
                    'tax' => $remainingTax,
                ];
                $details[] = [
                    'label' => (string)$orderFee->getFeeLabel(),
                    'fee' => $remainingFee,
                    'base_fee' => $baseRemainingFee,
                    'tax' => $remainingTax,
                    'base_tax' => $baseRemainingTax,
                ];
            }

            $invoice->setData(self::ALLOCATIONS_KEY, $allocations);
            $invoice->setData(DocumentFeeTotals::DETAILS_KEY, $this->json->serialize($details));

            if (($chargedTax > 0.0 || $baseChargedTax > 0.0) && !$invoice->isLast()) {
                $invoice->setTaxAmount((float)$invoice->getTaxAmount() + $chargedTax);
                $invoice->setBaseTaxAmount((float)$invoice->getBaseTaxAmount() + $baseChargedTax);
                $invoice->setGrandTotal($invoice->getGrandTotal() + $chargedTax);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseChargedTax);
            }

            if ($baseTotalFee > 0.0 || $totalFee > 0.0) {
                $invoice->setGrandTotal($invoice->getGrandTotal() + $totalFee);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalFee);

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
