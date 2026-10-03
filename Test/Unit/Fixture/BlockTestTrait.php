<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Fixture;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;

/**
 * Creates blocks without the framework constructor and wires a layout stub
 * so getParentBlock() returns the given parent.
 */
trait BlockTestTrait
{
    use ObjectHelperTrait;

    /**
     * @param array<string, mixed> $privateProps property name => value, declared on $class
     */
    protected function block(string $class, array $privateProps, $parent = null, array $data = []): AbstractBlock
    {
        $block = $this->newWithoutConstructor($class);
        foreach ($privateProps as $name => $value) {
            $this->inject($block, $class, $name, $value);
        }
        foreach ($data as $key => $value) {
            $block->setData($key, $value);
        }

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getParentName')->willReturn($parent ? 'parent.block' : false);
        $layout->method('getBlock')->willReturn($parent ?: false);
        $this->inject($block, AbstractBlock::class, '_layout', $layout);
        $this->inject($block, AbstractBlock::class, '_nameInLayout', 'panth.extra.fee');

        return $block;
    }
}
