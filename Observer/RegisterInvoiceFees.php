<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\ExtraFee\Model\OrderFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\Total\Invoice\ExtraFee as InvoiceTotal;
use Psr\Log\LoggerInterface;

class RegisterInvoiceFees implements ObserverInterface
{
    public function __construct(
        private readonly OrderFeeFactory $orderFeeFactory,
        private readonly OrderFeeResource $orderFeeResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $invoice = $observer->getEvent()->getInvoice();
        if (!$invoice) {
            return;
        }

        $allocations = $invoice->getData(InvoiceTotal::ALLOCATIONS_KEY);
        if (!is_array($allocations) || empty($allocations)) {
            return;
        }

        foreach ($allocations as $orderFeeId => $allocation) {
            try {
                $orderFee = $this->orderFeeFactory->create();
                $this->orderFeeResource->load($orderFee, (int)$orderFeeId);
                if (!$orderFee->getId()) {
                    continue;
                }
                $orderFee->setBaseFeeInvoiced(
                    (float)$orderFee->getBaseFeeInvoiced() + (float)$allocation['base_fee']
                );
                $orderFee->setFeeInvoiced((float)$orderFee->getFeeInvoiced() + (float)$allocation['fee']);
                $this->orderFeeResource->save($orderFee);
            } catch (\Exception $e) {
                $this->logger->error('Panth_ExtraFee: Error registering invoiced fee: ' . $e->getMessage());
            }
        }

        $invoice->setData(InvoiceTotal::ALLOCATIONS_KEY, []);
    }
}
