<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model\Pdf\Total;

use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\DocumentFeeTotals;

class ExtraFee extends DefaultTotal
{
    public function __construct(
        TaxHelper $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        private readonly DocumentFeeTotals $documentFeeTotals,
        private readonly Helper $helper,
        array $data = []
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    public function canDisplay()
    {
        return $this->getTotalsForDisplay() !== [];
    }

    public function getTotalsForDisplay()
    {
        $source = $this->getSource();
        $order = $this->getOrder();
        if (!$source || !$order) {
            return [];
        }

        $storeId = (int)$order->getStoreId();
        $title = $this->helper->getFeeDisplayTitle($storeId) ?: (string)__('Additional Fees');
        $rows = $this->documentFeeTotals->getRows($source);
        if ($rows === null) {
            $amount = (float)$source->getData('panth_extra_fee_amount');
            $rows = $amount > 0.0 ? [['label' => $title, 'fee' => $amount, 'tax' => 0.0]] : [];
        }

        if (!$this->helper->isShowFeeBreakdown($storeId) && count($rows) > 1) {
            $sum = ['label' => $title, 'fee' => 0.0, 'tax' => 0.0];
            foreach ($rows as $row) {
                $sum['fee'] += (float)$row['fee'];
                $sum['tax'] += (float)($row['tax'] ?? 0.0);
            }
            $rows = [$sum];
        }

        $showZero = $this->helper->isShowZeroFees($storeId);
        $inclTax = $this->helper->getTaxDisplay($storeId) === 2;
        $fontSize = $this->getFontSize() ?: 7;

        $lines = [];
        foreach ($rows as $row) {
            $value = (float)$row['fee'] + ($inclTax ? (float)($row['tax'] ?? 0.0) : 0.0);
            if (!$showZero && $value <= 0.0001) {
                continue;
            }
            $label = (string)($row['label'] ?? '');
            $lines[] = [
                'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($value),
                'label' => ($label !== '' ? $label : $title) . ':',
                'font_size' => $fontSize,
            ];
        }

        return $lines;
    }
}
