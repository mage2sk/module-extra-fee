<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Ui\Component\Listing\Column;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class OrderLink extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $backendUrl,
        private readonly OrderRepositoryInterface $orderRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $orderId = (int)($item['order_id'] ?? 0);
            if ($orderId === 0) {
                continue;
            }

            try {
                $order = $this->orderRepository->get($orderId);
                $incrementId = $order->getIncrementId();
                $url = $this->backendUrl->getUrl('sales/order/view', ['order_id' => $orderId]);
                $item[$this->getData('name')] = '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" title="View Order #' . htmlspecialchars($incrementId, ENT_QUOTES) . '">#' . htmlspecialchars($incrementId, ENT_QUOTES) . '</a>';
            } catch (\Throwable $e) {
                $item[$this->getData('name')] = '#' . $orderId;
            }
        }

        return $dataSource;
    }
}
