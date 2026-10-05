<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Sales\Order\Items\Column;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;

class ExtraFee extends DefaultColumn
{
    private array $breakdownCache = [];

    private OrderFeeCollectionFactory $orderFeeCollectionFactory;
    private Json $json;
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        Context $context,
        StockRegistryInterface $stockRegistry,
        StockConfigurationInterface $stockConfiguration,
        Registry $registry,
        OptionFactory $optionFactory,
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        Json $json,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->json = $json;
        $this->priceCurrency = $priceCurrency;
        parent::__construct($context, $stockRegistry, $stockConfiguration, $registry, $optionFactory, $data);
    }

    public function getItemExtraFee(): float
    {
        $item = $this->getItem();
        if (!$item || !$item->getId()) {
            return 0.0;
        }

        $orderId = (int)$item->getOrderId();
        if ($orderId <= 0) {
            return 0.0;
        }

        $breakdown = $this->getOrderBreakdown($orderId);
        $baseFee = (float)($breakdown[(int)$item->getId()] ?? 0.0);
        if ($baseFee <= 0.0) {
            return 0.0;
        }

        $order = $this->getOrder();
        $rate = $order ? (float)$order->getBaseToOrderRate() : 0.0;

        return $rate > 0.0 ? $baseFee * $rate : $baseFee;
    }

    public function getFormattedItemExtraFee(): string
    {
        $fee = $this->getItemExtraFee();
        if ($fee <= 0.0) {
            return '';
        }

        $order = $this->getOrder();
        $currencyCode = $order ? (string)$order->getOrderCurrencyCode() : 'USD';

        return $this->priceCurrency->format(
            $fee,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    private function getOrderBreakdown(int $orderId): array
    {
        if (isset($this->breakdownCache[$orderId])) {
            return $this->breakdownCache[$orderId];
        }

        $totals = [];
        $collection = $this->orderFeeCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        foreach ($collection as $orderFee) {
            $raw = (string)$orderFee->getData('item_breakdown');
            if ($raw === '') {
                continue;
            }
            try {
                $items = $this->json->unserialize($raw);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $orderItemId => $amount) {
                $totals[(int)$orderItemId] = ($totals[(int)$orderItemId] ?? 0.0) + (float)$amount;
            }
        }

        $this->breakdownCache[$orderId] = $totals;
        return $totals;
    }
}
