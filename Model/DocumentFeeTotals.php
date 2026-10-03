<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Panth\ExtraFee\Helper\Data as Helper;

class DocumentFeeTotals
{
    public const DETAILS_KEY = 'panth_extra_fee_details';

    public function __construct(
        private readonly Helper $helper,
        private readonly Json $json
    ) {
    }

    public function getRows($source): ?array
    {
        if (!$source instanceof Invoice && !$source instanceof Creditmemo) {
            return null;
        }

        $details = $source->getData(self::DETAILS_KEY);
        if (is_string($details) && $details !== '') {
            try {
                $details = $this->json->unserialize($details);
            } catch (\InvalidArgumentException $e) {
                return null;
            }
        }
        if (!is_array($details)) {
            return null;
        }

        $rows = [];
        foreach ($details as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'label' => (string)($row['label'] ?? ''),
                'fee' => (float)($row['fee'] ?? 0),
                'base_fee' => (float)($row['base_fee'] ?? 0),
                'tax' => (float)($row['tax'] ?? 0),
                'base_tax' => (float)($row['base_tax'] ?? 0),
            ];
        }

        return $rows;
    }

    public function addTotals($parent, array $rows, ?int $storeId = null): void
    {
        $taxDisplay = $this->helper->getTaxDisplay($storeId);
        $showZero = $this->helper->isShowZeroFees($storeId);

        if ($this->helper->isShowFeeBreakdown($storeId)) {
            $index = 0;
            foreach ($rows as $row) {
                if (!$showZero && $row['fee'] <= 0.0001) {
                    continue;
                }
                $this->addRow($parent, 'panth_extra_fee_' . $index, $row['label'], $row, $taxDisplay);
                $index++;
            }
            return;
        }

        $sum = ['fee' => 0.0, 'base_fee' => 0.0, 'tax' => 0.0, 'base_tax' => 0.0];
        foreach ($rows as $row) {
            foreach (array_keys($sum) as $key) {
                $sum[$key] += $row[$key];
            }
        }
        if (!$showZero && $sum['fee'] <= 0.0001) {
            return;
        }
        $this->addRow($parent, 'panth_extra_fee', $this->helper->getFeeDisplayTitle($storeId), $sum, $taxDisplay);
    }

    private function addRow($parent, string $code, string $label, array $row, int $taxDisplay): void
    {
        if ($taxDisplay === 1) {
            $parent->addTotal(new DataObject([
                'code' => $code,
                'value' => $row['fee'],
                'base_value' => $row['base_fee'],
                'label' => __($label),
            ]), 'tax');
            return;
        }

        if ($taxDisplay === 2) {
            $parent->addTotal(new DataObject([
                'code' => $code,
                'value' => $row['fee'] + $row['tax'],
                'base_value' => $row['base_fee'] + $row['base_tax'],
                'label' => __('%1 (Incl. Tax)', $label),
            ]), 'tax');
            return;
        }

        $parent->addTotal(new DataObject([
            'code' => $code . '_excl',
            'value' => $row['fee'],
            'base_value' => $row['base_fee'],
            'label' => __('%1 (Excl. Tax)', $label),
        ]), 'tax');
        $parent->addTotal(new DataObject([
            'code' => $code . '_incl',
            'value' => $row['fee'] + $row['tax'],
            'base_value' => $row['base_fee'] + $row['base_tax'],
            'label' => __('%1 (Incl. Tax)', $label),
        ]), 'tax');
    }
}
