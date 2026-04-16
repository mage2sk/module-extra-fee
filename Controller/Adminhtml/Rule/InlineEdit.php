<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;

class InlineEdit extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::manage_rules';

    /**
     * @var FeeRuleRepositoryInterface
     */
    private FeeRuleRepositoryInterface $feeRuleRepository;

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @param Context $context
     * @param FeeRuleRepositoryInterface $feeRuleRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        FeeRuleRepositoryInterface $feeRuleRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->feeRuleRepository = $feeRuleRepository;
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * Process inline edit
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        $items = $this->getRequest()->getParam('items', []);
        if (!($this->getRequest()->getParam('isAjax') && !empty($items))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        foreach ($items as $ruleId => $ruleData) {
            try {
                $rule = $this->feeRuleRepository->getById((int)$ruleId);
                $rule->setData(array_merge($rule->getData(), $ruleData));
                $this->feeRuleRepository->save($rule);
            } catch (LocalizedException $e) {
                $messages[] = __('[Rule ID: %1] %2', $ruleId, $e->getMessage());
                $error = true;
            } catch (\Exception $e) {
                $messages[] = __('[Rule ID: %1] Something went wrong while saving.', $ruleId);
                $error = true;
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error,
        ]);
    }
}
