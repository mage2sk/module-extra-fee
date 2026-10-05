<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Controller\Adminhtml\OrderFee;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Panth_ExtraFee::view_order_fees';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_ExtraFee::order_fees');
        $resultPage->getConfig()->getTitle()->prepend(__('Order Fees'));

        return $resultPage;
    }
}
