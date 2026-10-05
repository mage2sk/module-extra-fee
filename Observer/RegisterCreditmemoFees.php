<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\ExtraFee\Model\OrderFeeFactory;
use Panth\ExtraFee\Model\ResourceModel\OrderFee as OrderFeeResource;
use Panth\ExtraFee\Model\Total\Creditmemo\ExtraFee as CreditmemoTotal;
use Psr\Log\LoggerInterface;

class RegisterCreditmemoFees implements ObserverInterface
{
    public function __construct(
        private readonly OrderFeeFactory $orderFeeFactory,
        private readonly OrderFeeResource $orderFeeResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo) {
            return;
        }

        $allocations = $creditmemo->getData(CreditmemoTotal::ALLOCATIONS_KEY);
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
                $orderFee->setBaseFeeRefunded(
                    (float)$orderFee->getBaseFeeRefunded() + (float)$allocation['base_fee']
                );
                $orderFee->setFeeRefunded((float)$orderFee->getFeeRefunded() + (float)$allocation['fee']);
                $orderFee->setBaseTaxRefunded(
                    (float)$orderFee->getBaseTaxRefunded() + (float)$allocation['base_tax']
                );
                $orderFee->setTaxRefunded((float)$orderFee->getTaxRefunded() + (float)$allocation['tax']);
                $this->orderFeeResource->save($orderFee);
            } catch (\Exception $e) {
                $this->logger->error('Panth_ExtraFee: Error registering refunded fee: ' . $e->getMessage());
            }
        }

        $creditmemo->setData(CreditmemoTotal::ALLOCATIONS_KEY, []);
    }
}
