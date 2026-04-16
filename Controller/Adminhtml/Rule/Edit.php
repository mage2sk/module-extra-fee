<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;

class Edit extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::manage_rules';

    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;

    /**
     * @var FeeRuleRepositoryInterface
     */
    private FeeRuleRepositoryInterface $feeRuleRepository;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param FeeRuleRepositoryInterface $feeRuleRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FeeRuleRepositoryInterface $feeRuleRepository
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->feeRuleRepository = $feeRuleRepository;
    }

    /**
     * Edit or create fee rule page
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');

        if ($ruleId) {
            try {
                $feeRule = $this->feeRuleRepository->getById($ruleId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This fee rule no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_ExtraFee::manage_rules');

        $title = $ruleId
            ? __('Edit Fee Rule: %1', $feeRule->getName())
            : __('New Fee Rule');

        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
