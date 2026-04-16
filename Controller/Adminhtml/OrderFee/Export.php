<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\OrderFee;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Model\Export\ConvertToCsv;

class Export extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::view_order_fees';

    /**
     * @var ConvertToCsv
     */
    private ConvertToCsv $convertToCsv;

    /**
     * @var FileFactory
     */
    private FileFactory $fileFactory;

    /**
     * @param Context $context
     * @param ConvertToCsv $convertToCsv
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        ConvertToCsv $convertToCsv,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->convertToCsv = $convertToCsv;
        $this->fileFactory = $fileFactory;
    }

    /**
     * Export order fees to CSV
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        try {
            return $this->fileFactory->create(
                'order_fees.csv',
                $this->convertToCsv->getCsvFile(),
                'var'
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while exporting order fees.'));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
}
