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

class MassDelete extends Action implements HttpPostActionInterface
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
     * Mass delete fee rules
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }

        $deleted = 0;
        $errors = 0;

        foreach ($collection as $rule) {
            try {
                $this->feeRuleRepository->delete($rule);
                $deleted++;
            } catch (\Exception $e) {
                $errors++;
            }
        }

        if ($deleted) {
            $this->messageManager->addSuccessMessage(
                $deleted === 1
                    ? __('1 fee rule has been deleted.')
                    : __('%1 fee rules have been deleted.', $deleted)
            );
        }

        if ($errors) {
            $this->messageManager->addErrorMessage(
                $errors === 1
                    ? __('1 fee rule could not be deleted.')
                    : __('%1 fee rules could not be deleted.', $errors)
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
