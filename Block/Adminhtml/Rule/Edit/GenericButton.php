<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Rule\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class GenericButton
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Context
     */
    private Context $context;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Get rule ID from request
     *
     * @return int|null
     */
    public function getRuleId(): ?int
    {
        $ruleId = $this->context->getRequest()->getParam('rule_id');
        return $ruleId ? (int)$ruleId : null;
    }

    /**
     * Generate URL by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
