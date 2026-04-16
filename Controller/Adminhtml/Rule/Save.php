<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Model\FeeRuleFactory;

class Save extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::manage_rules';

    /**
     * Multi-select fields that need array-to-string conversion
     */
    private const MULTI_SELECT_FIELDS = [
        'payment_methods',
        'customer_groups',
        'countries',
        'regions',
        'category_ids',
        'store_ids',
        'website_ids',
    ];

    /**
     * @var FeeRuleRepositoryInterface
     */
    private FeeRuleRepositoryInterface $feeRuleRepository;

    /**
     * @var FeeRuleFactory
     */
    private FeeRuleFactory $feeRuleFactory;

    /**
     * @var DataPersistorInterface
     */
    private DataPersistorInterface $dataPersistor;

    /**
     * @param Context $context
     * @param FeeRuleRepositoryInterface $feeRuleRepository
     * @param FeeRuleFactory $feeRuleFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        FeeRuleRepositoryInterface $feeRuleRepository,
        FeeRuleFactory $feeRuleFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->feeRuleRepository = $feeRuleRepository;
        $this->feeRuleFactory = $feeRuleFactory;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Save fee rule
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $ruleId = isset($data['rule_id']) ? (int)$data['rule_id'] : null;

        try {
            if ($ruleId) {
                $model = $this->feeRuleRepository->getById($ruleId);
            } else {
                $model = $this->feeRuleFactory->create();
                unset($data['rule_id']);
            }

            $data = $this->prepareMultiSelectData($data);
            $model->setData(array_merge($model->getData(), $data));

            $this->feeRuleRepository->save($model);
            $this->messageManager->addSuccessMessage(__('The fee rule has been saved.'));
            $this->dataPersistor->clear('panth_extra_fee_rule');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['rule_id' => $model->getRuleId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the fee rule.'));
        }

        $this->dataPersistor->set('panth_extra_fee_rule', $data);

        if ($ruleId) {
            return $resultRedirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
        }

        return $resultRedirect->setPath('*/*/new');
    }

    /**
     * Convert multi-select array values to comma-separated strings
     *
     * @param array $data
     * @return array
     */
    private function prepareMultiSelectData(array $data): array
    {
        foreach (self::MULTI_SELECT_FIELDS as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = implode(',', $data[$field]);
            }
        }

        return $data;
    }
}
