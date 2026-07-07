<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Block\Adminhtml\Rule\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getRuleId()) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => 'deleteConfirm(\''
                . __('Are you sure you want to delete this fee rule?')
                . '\', \'' . $this->getDeleteUrl() . '\', {data: {}})',
            'sort_order' => 20,
        ];
    }

    private function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['rule_id' => $this->getRuleId()]);
    }
}
