<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\Total\Creditmemo;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Model\DocumentFeeTotals;
use Panth\ExtraFee\Model\OrderFee;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Psr\Log\LoggerInterface;

class ExtraFee extends AbstractTotal
{
    public const ALLOCATIONS_KEY = 'panth_extra_fee_allocations';

    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    private FeeRuleRepositoryInterface $feeRuleRepository;

    private LoggerInterface $logger;

    private Json $json;

    public function __construct(
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        FeeRuleRepositoryInterface $feeRuleRepository,
        LoggerInterface $logger,
        Json $json,
        array $data = []
    ) {
        parent::__construct($data);
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->feeRuleRepository = $feeRuleRepository;
        $this->logger = $logger;
        $this->json = $json;
    }

    public function collect(Creditmemo $creditmemo): self
    {
        parent::collect($creditmemo);

        $creditmemo->setData(self::ALLOCATIONS_KEY, []);

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
            $chargedTax = 0.0;
            $baseChargedTax = 0.0;
            $allocations = [];
            $details = [];

            foreach ($collection as $orderFee) {
                $feeType = (string)$orderFee->getFeeType();
                if ($feeType !== 'small_order') {
                    $ruleId = (int)$orderFee->getRuleId();
                    if ($ruleId > 0 && !$this->isRuleRefundable($ruleId)) {
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

                $baseRemainingTax = max($baseRemainingTax, 0.0);
                $remainingTax = max($remainingTax, 0.0);

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

            $creditmemo->setData(self::ALLOCATIONS_KEY, $allocations);
            $creditmemo->setData(DocumentFeeTotals::DETAILS_KEY, $this->json->serialize($details));

            if (($chargedTax > 0.0 || $baseChargedTax > 0.0) && !$creditmemo->isLast()) {
                $creditmemo->setTaxAmount((float)$creditmemo->getTaxAmount() + $chargedTax);
                $creditmemo->setBaseTaxAmount((float)$creditmemo->getBaseTaxAmount() + $baseChargedTax);
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $chargedTax);
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseChargedTax);
            }

            if ($baseTotalFee > 0.0 || $totalFee > 0.0) {
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalFee);
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalFee);

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

    private function isRuleRefundable(int $ruleId): bool
    {
        try {
            return (bool)$this->feeRuleRepository->getById($ruleId)->getIsRefundable();
        } catch (NoSuchEntityException $e) {
            return true;
        }
    }
}
