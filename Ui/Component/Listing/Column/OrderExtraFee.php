<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;

class OrderExtraFee extends Column
{
    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    private PriceCurrencyInterface $priceCurrency;

    private ExtraFeeHelper $helper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderFeeCollectionFactory $orderFeeCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        ExtraFeeHelper $helper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->orderFeeCollectionFactory = $orderFeeCollectionFactory;
        $this->priceCurrency = $priceCurrency;
        $this->helper = $helper;
    }

    public function prepare(): void
    {
        parent::prepare();

        if (!$this->helper->isEnabled() || !$this->helper->isShowInOrderGrid()) {
            $config = $this->getData('config');
            $config['componentDisabled'] = true;
            $this->setData('config', $config);
        }
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $orderId = isset($item['entity_id']) ? (int) $item['entity_id'] : 0;
            if ($orderId === 0) {
                $item[$fieldName] = '-';
                continue;
            }

            $collection = $this->orderFeeCollectionFactory->create();
            $collection->addOrderFilter($orderId);

            $totalFee = 0.0;
            foreach ($collection as $orderFee) {
                $totalFee += (float) $orderFee->getFeeAmount();
            }

            if ($totalFee > 0) {
                $currency = $item['order_currency_code'] ?? null;
                $item[$fieldName] = $this->priceCurrency->format(
                    $totalFee,
                    false,
                    PriceCurrencyInterface::DEFAULT_PRECISION,
                    null,
                    $currency
                );
            } else {
                $item[$fieldName] = '-';
            }
        }

        return $dataSource;
    }
}
