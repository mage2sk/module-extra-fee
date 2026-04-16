<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Panth\ExtraFee\Model\OrderFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee\CollectionFactory as QuoteFeeCollectionFactory;
use Panth\ExtraFee\Model\ResourceModel\QuoteFee as QuoteFeeResource;
use Psr\Log\LoggerInterface;

class SaveOrderFees implements ObserverInterface
{
    public function __construct(
        private readonly OrderFeeFactory $orderFeeFactory,
        private readonly OrderFeeResource $orderFeeResource,
        private readonly QuoteFeeCollectionFactory $quoteFeeCollectionFactory,
        private readonly QuoteFeeResource $quoteFeeResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();

            if ($order && $order->getId()) {
                $this->transferQuoteFeesToOrder($order);
                return;
            }

            // Multi-shipping support
            $orders = $observer->getEvent()->getOrders();
            if (!empty($orders)) {
                foreach ($orders as $o) {
                    if ($o instanceof Order && $o->getId()) {
                        $this->transferQuoteFeesToOrder($o);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Panth_ExtraFee: Observer error: ' . $e->getMessage());
        }
    }

    private function transferQuoteFeesToOrder(Order $order): void
    {
        $quoteId = (int)$order->getQuoteId();
        $orderId = (int)$order->getId();

        if (!$quoteId || !$orderId) {
            return;
        }

        // Check if fees already transferred (avoid duplicates on re-runs)
        $existingCheck = $this->quoteFeeCollectionFactory->create();
        // Actually check order fees table
        $conn = $this->orderFeeResource->getConnection();
        $existing = (int)$conn->fetchOne(
            $conn->select()
                ->from($this->orderFeeResource->getMainTable(), ['COUNT(*)'])
                ->where('order_id = ?', $orderId)
        );
        if ($existing > 0) {
            return;
        }

        $quoteFeeCollection = $this->quoteFeeCollectionFactory->create();
        $quoteFeeCollection->addFieldToFilter('quote_id', $quoteId);

        if ($quoteFeeCollection->getSize() === 0) {
            return;
        }

        foreach ($quoteFeeCollection as $quoteFee) {
            $baseFeeAmount = (float)$quoteFee->getData('base_fee_amount');
            $feeAmount = (float)$quoteFee->getData('fee_amount');
            $baseTaxAmount = (float)$quoteFee->getData('base_tax_amount');
            $taxAmount = (float)$quoteFee->getData('tax_amount');

            $orderFee = $this->orderFeeFactory->create();
            $orderFee->setData([
                'order_id' => $orderId,
                'quote_id' => $quoteId,
                'rule_id' => $quoteFee->getData('rule_id'),
                'fee_label' => $quoteFee->getData('fee_label'),
                'fee_type' => $quoteFee->getData('fee_type'),
                'base_fee_amount' => $baseFeeAmount,
                'fee_amount' => $feeAmount,
                'base_tax_amount' => $baseTaxAmount,
                'tax_amount' => $taxAmount,
                'base_fee_amount_incl_tax' => $baseFeeAmount + $baseTaxAmount,
                'fee_amount_incl_tax' => $feeAmount + $taxAmount,
            ]);

            $this->orderFeeResource->save($orderFee);
        }

        $this->logger->info("Panth_ExtraFee: Transferred {$quoteFeeCollection->getSize()} fees to order #{$order->getIncrementId()}");
    }
}
