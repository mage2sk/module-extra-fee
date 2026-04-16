<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Sales\Order\Creditmemo\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;
use Panth\ExtraFee\Model\FeeRuleRepository;

/**
 * Admin creditmemo view extra fee totals block.
 * Only shows refundable fees with remaining refundable amounts.
 */
class ExtraFee extends Template
{
    /**
     * @var ExtraFeeHelper
     */
    private ExtraFeeHelper $helper;

    /**
     * @var OrderFeeCollectionFactory
     */
    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    /**
     * @var FeeRuleRepository
     */
    private FeeRuleRepository $feeRuleRepository;

    /**
     * @param Context $context
     * @param ExtraFeeHelper $helper
     * @param OrderFeeCollectionFactory $orderFeeCollectionFactory
     * @param FeeRuleRepository $feeRuleRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        ExtraFeeHelper $helper,
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        FeeRuleRepository $feeRuleRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->feeRuleRepository = $feeRuleRepository;
    }

    /**
     * Initialize extra fee totals for the admin creditmemo view.
     *
     * @return $this
     */
    public function initTotals(): self
    {
        if (!$this->helper->isEnabled()) {
            return $this;
        }

        $parent = $this->getParentBlock();
        if (!$parent) {
            return $this;
        }

        $source = $parent->getSource();
        if (!$source) {
            return $this;
        }

        $order = $parent->getOrder();
        if (!$order || !$order->getId()) {
            return $this;
        }

        $collection = $this->orderFeeCollectionFactory->create();
        $collection->addOrderFilter((int) $order->getId());

        if ($collection->getSize() === 0) {
            return $this;
        }

        $taxDisplay = $this->helper->getTaxDisplay();
        $showBreakdown = $this->helper->isShowFeeBreakdown();

        if ($showBreakdown) {
            $this->addBreakdownTotals($parent, $collection, $taxDisplay);
        } else {
            $this->addAggregatedTotal($parent, $collection, $taxDisplay);
        }

        return $this;
    }

    /**
     * Check if a fee is refundable based on its rule.
     *
     * @param \Panth\ExtraFee\Model\OrderFee $orderFee
     * @return bool
     */
    private function isRefundable($orderFee): bool
    {
        $ruleId = $orderFee->getRuleId();
        if ($ruleId === null) {
            return true;
        }

        try {
            $rule = $this->feeRuleRepository->getById($ruleId);
            return (bool) $rule->getData('is_refundable');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return true;
        }
    }

    /**
     * Get refundable amounts remaining for a fee.
     *
     * @param \Panth\ExtraFee\Model\OrderFee $orderFee
     * @return array [fee_amount, base_fee_amount, tax_amount, base_tax_amount]
     */
    private function getRefundableAmounts($orderFee): array
    {
        $feeInvoiced = (float) $orderFee->getData('fee_invoiced');
        $feeRefunded = (float) $orderFee->getData('fee_refunded');
        $baseFeeInvoiced = (float) $orderFee->getData('base_fee_invoiced');
        $baseFeeRefunded = (float) $orderFee->getData('base_fee_refunded');
        $taxAmount = (float) $orderFee->getData('tax_amount');
        $baseTaxAmount = (float) $orderFee->getData('base_tax_amount');
        $taxRefunded = (float) $orderFee->getData('tax_refunded');
        $baseTaxRefunded = (float) $orderFee->getData('base_tax_refunded');

        return [
            $feeInvoiced - $feeRefunded,
            $baseFeeInvoiced - $baseFeeRefunded,
            $taxAmount - $taxRefunded,
            $baseTaxAmount - $baseTaxRefunded,
        ];
    }

    /**
     * Add individual fee totals for each refundable fee line.
     *
     * @param \Magento\Framework\View\Element\AbstractBlock $parent
     * @param \Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection $collection
     * @param int $taxDisplay
     * @return void
     */
    private function addBreakdownTotals($parent, $collection, int $taxDisplay): void
    {
        $index = 0;
        foreach ($collection as $fee) {
            if (!$this->isRefundable($fee)) {
                continue;
            }

            [$feeAmount, $baseFeeAmount, $taxAmount, $baseTaxAmount] = $this->getRefundableAmounts($fee);
            $label = (string) $fee->getData('fee_label');

            if (!$this->helper->isShowZeroFees() && $feeAmount <= 0.0001) {
                continue;
            }

            $code = 'panth_extra_fee_' . $index;

            if ($taxDisplay === 1) {
                $parent->addTotal(new DataObject([
                    'code'       => $code,
                    'value'      => $feeAmount,
                    'base_value' => $baseFeeAmount,
                    'label'      => __($label),
                ]), 'tax');
            } elseif ($taxDisplay === 2) {
                $parent->addTotal(new DataObject([
                    'code'       => $code,
                    'value'      => $feeAmount + $taxAmount,
                    'base_value' => $baseFeeAmount + $baseTaxAmount,
                    'label'      => __('%1 (Incl. Tax)', $label),
                ]), 'tax');
            } else {
                $parent->addTotal(new DataObject([
                    'code'       => $code . '_excl',
                    'value'      => $feeAmount,
                    'base_value' => $baseFeeAmount,
                    'label'      => __('%1 (Excl. Tax)', $label),
                ]), 'tax');
                $parent->addTotal(new DataObject([
                    'code'       => $code . '_incl',
                    'value'      => $feeAmount + $taxAmount,
                    'base_value' => $baseFeeAmount + $baseTaxAmount,
                    'label'      => __('%1 (Incl. Tax)', $label),
                ]), 'tax');
            }

            $index++;
        }
    }

    /**
     * Add a single combined refundable extra fees total.
     *
     * @param \Magento\Framework\View\Element\AbstractBlock $parent
     * @param \Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection $collection
     * @param int $taxDisplay
     * @return void
     */
    private function addAggregatedTotal($parent, $collection, int $taxDisplay): void
    {
        $totalFee = 0.0;
        $baseTotalFee = 0.0;
        $totalTax = 0.0;
        $baseTotalTax = 0.0;

        foreach ($collection as $fee) {
            if (!$this->isRefundable($fee)) {
                continue;
            }

            [$feeAmount, $baseFeeAmount, $taxAmount, $baseTaxAmount] = $this->getRefundableAmounts($fee);
            $totalFee += $feeAmount;
            $baseTotalFee += $baseFeeAmount;
            $totalTax += $taxAmount;
            $baseTotalTax += $baseTaxAmount;
        }

        if (!$this->helper->isShowZeroFees() && $totalFee <= 0.0001) {
            return;
        }

        $displayTitle = $this->helper->getFeeDisplayTitle();

        if ($taxDisplay === 1) {
            $parent->addTotal(new DataObject([
                'code'       => 'panth_extra_fee',
                'value'      => $totalFee,
                'base_value' => $baseTotalFee,
                'label'      => __($displayTitle),
            ]), 'tax');
        } elseif ($taxDisplay === 2) {
            $parent->addTotal(new DataObject([
                'code'       => 'panth_extra_fee',
                'value'      => $totalFee + $totalTax,
                'base_value' => $baseTotalFee + $baseTotalTax,
                'label'      => __('%1 (Incl. Tax)', $displayTitle),
            ]), 'tax');
        } else {
            $parent->addTotal(new DataObject([
                'code'       => 'panth_extra_fee_excl',
                'value'      => $totalFee,
                'base_value' => $baseTotalFee,
                'label'      => __('%1 (Excl. Tax)', $displayTitle),
            ]), 'tax');
            $parent->addTotal(new DataObject([
                'code'       => 'panth_extra_fee_incl',
                'value'      => $totalFee + $totalTax,
                'base_value' => $baseTotalFee + $baseTotalTax,
                'label'      => __('%1 (Incl. Tax)', $displayTitle),
            ]), 'tax');
        }
    }
}
