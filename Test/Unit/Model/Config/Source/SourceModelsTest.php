<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\CheckoutConfigProvider;
use Panth\ExtraFee\Model\Config\Source\ApplyPer;
use Panth\ExtraFee\Model\Config\Source\Countries;
use Panth\ExtraFee\Model\Config\Source\CustomerGroups;
use Panth\ExtraFee\Model\Config\Source\FeeStatus;
use Panth\ExtraFee\Model\Config\Source\FeeType;
use Panth\ExtraFee\Model\Config\Source\PaymentMethods;
use Panth\ExtraFee\Model\Config\Source\TaxDisplay;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    private function values(array $options): array
    {
        return array_column($options, 'value');
    }

    public function testStaticSourcesExposeTheValuesTheCalculatorUnderstands(): void
    {
        $this->assertSame(['order', 'product', 'quantity'], $this->values((new ApplyPer())->toOptionArray()));
        $this->assertSame(
            ['fixed', 'percent', 'combined', 'fixed_minimum'],
            $this->values((new FeeType())->toOptionArray())
        );
        $this->assertSame([1, 0], $this->values((new FeeStatus())->toOptionArray()));
        $this->assertSame([1, 2, 3], $this->values((new TaxDisplay())->toOptionArray()));
    }

    public function testEveryStaticOptionHasALabel(): void
    {
        $options = array_merge(
            (new ApplyPer())->toOptionArray(),
            (new FeeType())->toOptionArray(),
            (new FeeStatus())->toOptionArray(),
            (new TaxDisplay())->toOptionArray()
        );

        foreach ($options as $option) {
            $this->assertNotSame('', (string)$option['label']);
        }
    }

    public function testCountriesDropThePlaceholderOption(): void
    {
        $country = $this->createStub(Country::class);
        $country->method('toOptionArray')->willReturn([
            ['value' => '', 'label' => '--Please Select--'],
            ['value' => 'US', 'label' => 'United States'],
            ['value' => 'GB', 'label' => 'United Kingdom'],
        ]);

        $this->assertSame(['US', 'GB'], array_values($this->values((new Countries($country))->toOptionArray())));
    }

    public function testCustomerGroupsAreLoadedOnce(): void
    {
        $collection = $this->createStub(GroupCollection::class);
        $collection->method('toOptionArray')->willReturn([['value' => 0, 'label' => 'NOT LOGGED IN']]);
        $factory = $this->createMock(GroupCollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);
        $source = new CustomerGroups($factory);

        $this->assertSame([['value' => 0, 'label' => 'NOT LOGGED IN']], $source->toOptionArray());
        $this->assertSame([['value' => 0, 'label' => 'NOT LOGGED IN']], $source->toOptionArray());
    }

    public function testPaymentMethodsUseConfiguredTitlesSortedByLabel(): void
    {
        $paymentConfig = $this->createStub(PaymentConfig::class);
        $paymentConfig->method('getActiveMethods')->willReturn(['zcode' => null, 'checkmo' => null, 'free' => null]);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $titles = ['payment/zcode/title' => 'Bank Transfer', 'payment/checkmo/title' => 'Check / Money order'];
        $scopeConfig->method('getValue')->willReturnCallback(static fn($path) => $titles[$path] ?? null);

        $options = (new PaymentMethods($paymentConfig, $scopeConfig))->toOptionArray();

        $this->assertSame(
            [
                ['value' => 'zcode', 'label' => 'Bank Transfer'],
                ['value' => 'checkmo', 'label' => 'Check / Money order'],
                ['value' => 'free', 'label' => 'free'],
            ],
            $options
        );
    }

    public function testCheckoutConfigExposesDisplayFlagsForTheCurrentStore(): void
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(4);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $helper = $this->createStub(Helper::class);
        $helper->method('isShowInCart')->willReturnCallback(static fn($id) => $id === 4);
        $helper->method('isShowInCheckout')->willReturn(false);

        $config = (new CheckoutConfigProvider($helper, $storeManager))->getConfig();

        $this->assertSame(['panthExtraFee' => ['showInCart' => true, 'showInCheckout' => false]], $config);
    }
}
