<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\ExtraFee\Helper\Data as Helper;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getConfig(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        return [
            'panthExtraFee' => [
                'showInCart' => $this->helper->isShowInCart($storeId),
                'showInCheckout' => $this->helper->isShowInCheckout($storeId),
            ],
        ];
    }
}
