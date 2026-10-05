<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Fixture;

use Magento\Quote\Model\Quote;

/**
 * Quote test double that skips the heavy framework constructor and lets each
 * test inject the collaborators the module reads.
 */
class QuoteDouble extends Quote
{
    public $storeDouble;

    public $shippingDouble;

    public $billingDouble;

    public $paymentDouble;

    public array $visibleItems = [];

    public array $shippingAddresses = [];

    public bool $virtual = false;

    public $taxClassId = 3;

    public function __construct(array $data = [])
    {
        $this->_data = $data;
    }

    public function getStoreId()
    {
        return (int)($this->_data['store_id'] ?? 1);
    }

    public function getStore()
    {
        return $this->storeDouble;
    }

    public function getShippingAddress()
    {
        return $this->shippingDouble;
    }

    public function getBillingAddress()
    {
        return $this->billingDouble;
    }

    public function getPayment()
    {
        return $this->paymentDouble;
    }

    public function getAllVisibleItems()
    {
        return $this->visibleItems;
    }

    public function getAllShippingAddresses()
    {
        return $this->shippingAddresses;
    }

    public function isVirtual()
    {
        return $this->virtual;
    }

    public function getCustomerGroupId()
    {
        return $this->getData('customer_group_id');
    }

    public function getCustomerTaxClassId()
    {
        return $this->taxClassId;
    }
}
