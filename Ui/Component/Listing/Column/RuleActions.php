<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RuleActions extends Column
{
    private UrlInterface $urlBuilder;

    private Escaper $escaper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

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
                        'post' => true,
                        'confirm' => [
                            'title' => __('Delete Fee Rule'),
                            'message' => __(
                                'Are you sure you want to delete the fee rule "%1"?',
                                $this->escaper->escapeHtml((string)($item['name'] ?? ''))
                            ),
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
