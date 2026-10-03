<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Ui\Component\Listing;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panth\ExtraFee\Helper\Data as Helper;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\Collection;
use Panth\ExtraFee\Model\ResourceModel\OrderFee\CollectionFactory;
use Panth\ExtraFee\Ui\Component\Listing\Column\OrderExtraFee;
use Panth\ExtraFee\Ui\Component\Listing\Column\OrderLink;
use Panth\ExtraFee\Ui\Component\Listing\Column\RuleActions;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    use ObjectHelperTrait;

    private function context(): ContextInterface
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        return $context;
    }

    private function orderExtraFee(array $feesByOrder, bool $enabled = true, bool $showInGrid = true): OrderExtraFee
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($feesByOrder) {
            $current = [];
            $collection = $this->createStub(Collection::class);
            $collection->method('addOrderFilter')->willReturnCallback(
                function ($orderId) use (&$current, $feesByOrder, $collection) {
                    $current = array_map(
                        static fn($amount) => new DataObject(['fee_amount' => $amount]),
                        $feesByOrder[$orderId] ?? []
                    );
                    return $collection;
                }
            );
            $collection->method('getIterator')->willReturnCallback(static function () use (&$current) {
                return new \ArrayIterator($current);
            });
            return $collection;
        });

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn($amount, $includeContainer, $precision, $scope, $currency) =>
                ($currency ?? 'BASE') . ' ' . number_format((float)$amount, 2)
        );
        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturn($enabled);
        $helper->method('isShowInOrderGrid')->willReturn($showInGrid);

        return new OrderExtraFee(
            $this->context(),
            $this->createStub(UiComponentFactory::class),
            $factory,
            $priceCurrency,
            $helper,
            [],
            ['name' => 'panth_extra_fee']
        );
    }

    public function testOrderGridShowsSummedFeeInOrderCurrency(): void
    {
        $column = $this->orderExtraFee([7 => [2.5, 1.5], 8 => [0]]);

        $result = $column->prepareDataSource(['data' => ['items' => [
            ['entity_id' => 7, 'order_currency_code' => 'EUR'],
            ['entity_id' => 8],
            ['entity_id' => 0],
            [],
        ]]]);

        $this->assertSame(
            ['EUR 4.00', '-', '-', '-'],
            array_column($result['data']['items'], 'panth_extra_fee')
        );
    }

    public function testOrderGridDataWithoutItemsIsReturnedUnchanged(): void
    {
        $this->assertSame(['data' => []], $this->orderExtraFee([])->prepareDataSource(['data' => []]));
    }

    public function testOrderGridColumnIsDisabledWhenHidden(): void
    {
        $hidden = $this->orderExtraFee([], true, false);
        $hidden->prepare();
        $disabled = $this->orderExtraFee([], false, true);
        $disabled->prepare();
        $shown = $this->orderExtraFee([], true, true);
        $shown->prepare();

        $this->assertTrue($hidden->getData('config')['componentDisabled']);
        $this->assertTrue($disabled->getData('config')['componentDisabled']);
        $this->assertArrayNotHasKey('componentDisabled', (array)$shown->getData('config'));
    }

    public function testOrderLinkRendersEscapedLinkOrFallsBackToId(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('0001"x');
        $repository = $this->createStub(OrderRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(static function ($id) use ($order) {
            if ($id === 7) {
                return $order;
            }
            throw new NoSuchEntityException(__('gone'));
        });
        $url = $this->createStub(BackendUrl::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params) => 'https://admin.test/' . $route . '/id/' . $params['order_id']
        );

        $column = new OrderLink($this->context(), $this->createStub(UiComponentFactory::class), $url, $repository, [], [
            'name' => 'order_link',
        ]);
        $result = $column->prepareDataSource(['data' => ['items' => [
            ['order_id' => 7],
            ['order_id' => 9],
            ['order_id' => 0],
        ]]]);
        $items = $result['data']['items'];

        $this->assertSame(
            '<a href="https://admin.test/sales/order/view/id/7" target="_blank" title="View Order #0001&quot;x">#0001&quot;x</a>',
            $items[0]['order_link']
        );
        $this->assertSame('#9', $items[1]['order_link']);
        $this->assertArrayNotHasKey('order_link', $items[2]);
    }

    public function testRuleActionsBuildEditAndDeleteLinks(): void
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params) => $route . '/' . $params['rule_id']
        );
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v));

        $column = new RuleActions($this->context(), $this->createStub(UiComponentFactory::class), $url, $escaper, [], [
            'name' => 'actions',
        ]);
        $result = $column->prepareDataSource(['data' => ['items' => [
            ['rule_id' => 4, 'name' => '<b>COD</b>'],
            ['name' => 'no id'],
        ]]]);
        $actions = $result['data']['items'][0]['actions'];

        $this->assertSame('panth_extrafee/rule/edit/4', $actions['edit']['href']);
        $this->assertSame('panth_extrafee/rule/delete/4', $actions['delete']['href']);
        $this->assertTrue($actions['delete']['post']);
        $this->assertStringContainsString('&lt;b&gt;COD&lt;/b&gt;', (string)$actions['delete']['confirm']['message']);
        $this->assertArrayNotHasKey('actions', $result['data']['items'][1]);
    }
}
