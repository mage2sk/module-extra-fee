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

    private const MULTI_SELECT_FIELDS = [
        'payment_methods',
        'customer_groups',
        'countries',
        'store_ids',
        'website_ids',
    ];

    private const NULLABLE_FIELDS = [
        'min_fee_amount',
        'max_fee_amount',
        'min_order_subtotal',
        'max_order_subtotal',
        'min_order_qty',
        'max_order_qty',
        'date_from',
        'date_to',
    ];

    private const NON_NEGATIVE_FIELDS = [
        'fee_amount' => 'Fee Amount',
        'fee_amount_percent' => 'Fee Amount Percent',
        'min_fee_amount' => 'Minimum Fee Amount',
        'max_fee_amount' => 'Maximum Fee Amount',
        'min_order_subtotal' => 'Minimum Order Subtotal',
        'max_order_subtotal' => 'Maximum Order Subtotal',
        'min_order_qty' => 'Minimum Order Qty',
        'max_order_qty' => 'Maximum Order Qty',
    ];

    private const RANGE_FIELDS = [
        ['min_fee_amount', 'max_fee_amount', 'Minimum Fee Amount', 'Maximum Fee Amount'],
        ['min_order_subtotal', 'max_order_subtotal', 'Minimum Order Subtotal', 'Maximum Order Subtotal'],
        ['min_order_qty', 'max_order_qty', 'Minimum Order Qty', 'Maximum Order Qty'],
    ];

    private FeeRuleRepositoryInterface $feeRuleRepository;

    private FeeRuleFactory $feeRuleFactory;

    private DataPersistorInterface $dataPersistor;

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
            $data = $this->prepareNullableData($data);
            $this->validateData($data);
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

    private function prepareNullableData(array $data): array
    {
        foreach (self::NULLABLE_FIELDS as $field) {
            if (array_key_exists($field, $data) && trim((string)$data[$field]) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function validateData(array $data): void
    {
        foreach (self::NON_NEGATIVE_FIELDS as $field => $label) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                continue;
            }
            if (!is_numeric($data[$field]) || (float)$data[$field] < 0) {
                throw new LocalizedException(__('%1 must be a number of 0 or more.', __($label)));
            }
        }

        foreach (self::RANGE_FIELDS as [$minField, $maxField, $minLabel, $maxLabel]) {
            $min = (float)($data[$minField] ?? 0);
            $max = (float)($data[$maxField] ?? 0);
            if ($min > 0 && $max > 0 && $min > $max) {
                throw new LocalizedException(
                    __('%1 cannot be greater than %2.', __($minLabel), __($maxLabel))
                );
            }
        }

        $from = (string)($data['date_from'] ?? '');
        $to = (string)($data['date_to'] ?? '');
        if ($from !== '' && $to !== '' && strtotime($from) !== false && strtotime($to) !== false
            && strtotime($from) > strtotime($to)
        ) {
            throw new LocalizedException(__('From Date cannot be later than To Date.'));
        }
    }

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
