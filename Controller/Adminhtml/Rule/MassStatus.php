<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;

class MassStatus extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::manage_rules';

    /**
     * @var Filter
     */
    private Filter $filter;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var FeeRuleRepositoryInterface
     */
    private FeeRuleRepositoryInterface $feeRuleRepository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param FeeRuleRepositoryInterface $feeRuleRepository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        FeeRuleRepositoryInterface $feeRuleRepository
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->feeRuleRepository = $feeRuleRepository;
    }

    /**
     * Mass update status for fee rules
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $status = (int)$this->getRequest()->getParam('status');

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }

        $updated = 0;
        $errors = 0;

        foreach ($collection as $rule) {
            try {
                $rule->setIsActive((bool)$status);
                $this->feeRuleRepository->save($rule);
                $updated++;
            } catch (\Exception $e) {
                $errors++;
            }
        }

        if ($updated) {
            $statusLabel = $status ? __('enabled') : __('disabled');
            $this->messageManager->addSuccessMessage(
                $updated === 1
                    ? __('1 fee rule has been %1.', $statusLabel)
                    : __('%1 fee rules have been %2.', $updated, $statusLabel)
            );
        }

        if ($errors) {
            $this->messageManager->addErrorMessage(
                $errors === 1
                    ? __('1 fee rule could not be updated.')
                    : __('%1 fee rules could not be updated.', $errors)
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
