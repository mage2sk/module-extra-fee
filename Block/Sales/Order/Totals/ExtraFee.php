<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Block\Sales\Order\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;

/**
 * Extra fee totals block for order, invoice, and creditmemo views.
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
     * @param Context $context
     * @param ExtraFeeHelper $helper
     * @param OrderFeeCollectionFactory $orderFeeCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        ExtraFeeHelper $helper,
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
    }

    /**
     * Initialize totals. Called by the parent totals block via layout.
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
     * Add individual fee line totals to the parent block.
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
            $feeAmount = (float) $fee->getData('fee_amount');
            $baseFeeAmount = (float) $fee->getData('base_fee_amount');
            $taxAmount = (float) $fee->getData('tax_amount');
            $baseTaxAmount = (float) $fee->getData('base_tax_amount');
            $label = (string) $fee->getData('fee_label');

            if (!$this->helper->isShowZeroFees() && $feeAmount <= 0.0001) {
                continue;
            }

            $code = 'panth_extra_fee_' . $index;

            if ($taxDisplay === 1) {
                // Excluding tax
                $parent->addTotal(new DataObject([
                    'code'       => $code,
                    'value'      => $feeAmount,
                    'base_value' => $baseFeeAmount,
                    'label'      => __($label),
                ]), 'tax');
            } elseif ($taxDisplay === 2) {
                // Including tax
                $parent->addTotal(new DataObject([
                    'code'       => $code,
                    'value'      => $feeAmount + $taxAmount,
                    'base_value' => $baseFeeAmount + $baseTaxAmount,
                    'label'      => __('%1 (Incl. Tax)', $label),
                ]), 'tax');
            } else {
                // Both
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
     * Add a single aggregated total line to the parent block.
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
            $totalFee += (float) $fee->getData('fee_amount');
            $baseTotalFee += (float) $fee->getData('base_fee_amount');
            $totalTax += (float) $fee->getData('tax_amount');
            $baseTotalTax += (float) $fee->getData('base_tax_amount');
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
