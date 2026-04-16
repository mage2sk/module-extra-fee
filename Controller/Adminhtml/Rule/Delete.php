<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::manage_rules';

    /**
     * @var FeeRuleRepositoryInterface
     */
    private FeeRuleRepositoryInterface $feeRuleRepository;

    /**
     * @param Context $context
     * @param FeeRuleRepositoryInterface $feeRuleRepository
     */
    public function __construct(
        Context $context,
        FeeRuleRepositoryInterface $feeRuleRepository
    ) {
        parent::__construct($context);
        $this->feeRuleRepository = $feeRuleRepository;
    }

    /**
     * Delete fee rule
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ruleId = (int)$this->getRequest()->getParam('rule_id');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('We cannot find a fee rule to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->feeRuleRepository->deleteById($ruleId);
            $this->messageManager->addSuccessMessage(__('The fee rule has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the fee rule.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
