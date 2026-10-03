<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Rule\Edit;

use Magento\Backend\Block\Template;

class AssignProducts extends Template
{
    protected $_template = 'Panth_ExtraFee::rule/assign-products.phtml';

    public function getProductGridUrl(): string
    {
        return $this->getUrl('panth_extrafee/rule/productsgrid');
    }

    public function getCategoryTreeUrl(): string
    {
        return $this->getUrl('panth_extrafee/rule/categorytree');
    }

    public function getProductUrlSuffix(): string
    {
        return (string) $this->_scopeConfig->getValue(
            'catalog/seo/product_url_suffix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCategoryUrlSuffix(): string
    {
        return (string) $this->_scopeConfig->getValue(
            'catalog/seo/category_url_suffix',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
