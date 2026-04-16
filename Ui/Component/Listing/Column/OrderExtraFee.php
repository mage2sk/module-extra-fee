<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\ExtraFee\Helper\Data as ExtraFeeHelper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory as OrderFeeCollectionFactory;

/**
 * Order grid column showing total extra fees for each order.
 * Controlled by config: panth_extra_fee/display/show_in_order_grid
 */
class OrderExtraFee extends Column
{
    /**
     * @var OrderFeeCollectionFactory
     */
    private OrderFeeCollectionFactory $orderFeeCollectionFactory;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var ExtraFeeHelper
     */
    private ExtraFeeHelper $helper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderFeeCollectionFactory $orderFeeCollectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param ExtraFeeHelper $helper
     * @param array $components
     * @param array $data
     */
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

    /**
     * Conditionally hide column based on configuration.
     *
     * @return void
     */
    public function prepare(): void
    {
        parent::prepare();

        if (!$this->helper->isEnabled() || !$this->helper->isShowInOrderGrid()) {
            $config = $this->getData('config');
            $config['componentDisabled'] = true;
            $this->setData('config', $config);
        }
    }

    /**
     * Prepare data source with extra fee amounts per order.
     *
     * @param array $dataSource
     * @return array
     */
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
