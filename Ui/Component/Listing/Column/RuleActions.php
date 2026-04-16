<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RuleActions extends Column
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['rule_id'])) {
                    $name = $this->getData('name');
                    $item[$name]['edit'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'panth_extrafee/rule/edit',
                            ['rule_id' => $item['rule_id']]
                        ),
                        'label' => __('Edit'),
                    ];
                    $item[$name]['delete'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'panth_extrafee/rule/delete',
                            ['rule_id' => $item['rule_id']]
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete Fee Rule'),
                            'message' => __(
                                'Are you sure you want to delete the fee rule "%1"?',
                                $item['name'] ?? ''
                            ),
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
