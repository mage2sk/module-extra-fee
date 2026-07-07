<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Rule\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class GenericButton
{
    private UrlInterface $urlBuilder;

    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    public function getRuleId(): ?int
    {
        $ruleId = $this->context->getRequest()->getParam('rule_id');
        return $ruleId ? (int)$ruleId : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
