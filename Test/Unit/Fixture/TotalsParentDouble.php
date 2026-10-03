<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Fixture;

use Magento\Framework\DataObject;

/**
 * Stand-in for a totals parent block: records every total added to it.
 */
class TotalsParentDouble extends DataObject
{
    public array $totals = [];

    public function addTotal(DataObject $total, $after = null): void
    {
        $this->totals[] = ['total' => $total, 'after' => $after];
    }

    public function codes(): array
    {
        return array_map(static fn($t) => $t['total']->getData('code'), $this->totals);
    }

    public function values(): array
    {
        return array_map(static fn($t) => $t['total']->getData('value'), $this->totals);
    }

    public function labels(): array
    {
        return array_map(static fn($t) => (string)$t['total']->getData('label'), $this->totals);
    }
}
