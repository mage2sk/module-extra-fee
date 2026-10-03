<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\ExtraFee\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    private array $where = [];

    private function collection(): AbstractDb
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($c) => '`' . $c . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($condition) use ($select) {
            $this->where[] = $condition;
            return $select;
        });
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);
        return $collection;
    }

    private function filter($value): Filter
    {
        return new Filter(['value' => $value]);
    }

    public function testBuildsAnOrLikeConditionAcrossColumns(): void
    {
        (new LikeFulltextFilter(['name', 'fee_label', 42]))->apply($this->collection(), $this->filter('  cod  '));

        $this->assertSame(["`name` LIKE '%cod%' OR `fee_label` LIKE '%cod%'"], $this->where);
    }

    public function testLikeWildcardsInTheSearchAreEscaped(): void
    {
        (new LikeFulltextFilter(['name']))->apply($this->collection(), $this->filter('50%_off'));

        $this->assertSame(["`name` LIKE '%50\\%\\_off%'"], $this->where);
    }

    public function testBlankOrNonScalarValuesAreIgnored(): void
    {
        $filter = new LikeFulltextFilter(['name']);
        $filter->apply($this->collection(), $this->filter('   '));
        $filter->apply($this->collection(), $this->filter(['a']));

        $this->assertSame([], $this->where);
    }

    public function testNothingHappensWithoutColumnsOrForNonDbCollections(): void
    {
        (new LikeFulltextFilter([]))->apply($this->collection(), $this->filter('cod'));
        (new LikeFulltextFilter(['name']))->apply($this->createStub(Collection::class), $this->filter('cod'));

        $this->assertSame([], $this->where);
    }

    public function testSearchIsTruncatedTo200Characters(): void
    {
        (new LikeFulltextFilter(['name']))->apply($this->collection(), $this->filter(str_repeat('a', 300)));

        $this->assertSame(["`name` LIKE '%" . str_repeat('a', 200) . "%'"], $this->where);
    }
}
