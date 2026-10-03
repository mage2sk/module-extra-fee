<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Block\Adminhtml\Rule;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Panth\ExtraFee\Block\Adminhtml\Rule\Edit\AssignProducts;
use Panth\ExtraFee\Block\Adminhtml\Rule\Edit\DeleteButton;
use Panth\ExtraFee\Block\Adminhtml\Rule\Edit\GenericButton;
use Panth\ExtraFee\Block\Adminhtml\Rule\Edit\SaveAndContinueButton;
use Panth\ExtraFee\Block\Adminhtml\Rule\Edit\SaveButton;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

class ButtonsTest extends TestCase
{
    use ObjectHelperTrait;

    private function context($ruleId): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($name) => $name === 'rule_id' ? $ruleId : null);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => '/admin/' . $route . ($params ? '?' . http_build_query($params) : '')
        );
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getEscaper')->willReturn(new Escaper());
        return $context;
    }

    public function testGenericButtonReadsRuleIdAndBuildsUrls(): void
    {
        $button = new GenericButton($this->context('12'));

        $this->assertSame(12, $button->getRuleId());
        $this->assertSame('/admin/x/y?a=1', $button->getUrl('x/y', ['a' => 1]));
        $this->assertNull((new GenericButton($this->context(null)))->getRuleId());
        $this->assertNull((new GenericButton($this->context('0')))->getRuleId());
    }

    public function testDeleteButtonOnlyForExistingRules(): void
    {
        $this->assertSame([], (new DeleteButton($this->context(null)))->getButtonData());

        $data = (new DeleteButton($this->context('5')))->getButtonData();
        $this->assertSame('delete', $data['class']);
        $this->assertStringContainsString("'/admin/*/*/delete?rule_id=5'", $data['on_click']);
        $this->assertStringStartsWith('deleteConfirm(', $data['on_click']);
    }

    private function withApostropheTranslation(callable $callback)
    {
        $previous = \Magento\Framework\Phrase::getRenderer();
        \Magento\Framework\Phrase::setRenderer(new class implements \Magento\Framework\Phrase\RendererInterface {
            public function render(array $source, array $arguments)
            {
                return "It's gone: " . end($source);
            }
        });
        try {
            return $callback();
        } finally {
            \Magento\Framework\Phrase::setRenderer($previous);
        }
    }

    public function testDeleteButtonEscapesTranslatedTextForJavascript(): void
    {
        $data = $this->withApostropheTranslation(
            fn() => (new DeleteButton($this->context('5')))->getButtonData()
        );

        $this->assertStringNotContainsString("It's", $data['on_click']);
        $this->assertStringContainsString('It\u0027s', $data['on_click']);
        $this->assertStringContainsString("'/admin/*/*/delete?rule_id=5'", $data['on_click']);
    }

    public function testSaveButtonsTriggerTheRightFormEvents(): void
    {
        $save = (new SaveButton($this->context(null)))->getButtonData();
        $continue = (new SaveAndContinueButton($this->context(null)))->getButtonData();

        $this->assertSame('save', $save['data_attribute']['mage-init']['button']['event']);
        $this->assertSame('saveAndContinueEdit', $continue['data_attribute']['mage-init']['button']['event']);
        $this->assertGreaterThan($continue['sort_order'], $save['sort_order']);
    }

    public function testAssignProductsExposesAjaxUrlsAndSuffixes(): void
    {
        $block = $this->newWithoutConstructor(AssignProducts::class);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn($route) => '/admin/' . $route);
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn($path) => ['catalog/seo/product_url_suffix' => '.html'][$path] ?? null
        );
        $this->inject($block, AbstractBlock::class, '_urlBuilder', $url);
        $this->inject($block, AbstractBlock::class, '_scopeConfig', $scope);

        $this->assertSame('/admin/panth_extrafee/rule/productsgrid', $block->getProductGridUrl());
        $this->assertSame('/admin/panth_extrafee/rule/categorytree', $block->getCategoryTreeUrl());
        $this->assertSame('.html', $block->getProductUrlSuffix());
        $this->assertSame('', $block->getCategoryUrlSuffix());
    }
}
