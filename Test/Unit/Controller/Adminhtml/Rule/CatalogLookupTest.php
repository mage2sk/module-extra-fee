<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Controller\Adminhtml\Rule;

use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Model\Export\ConvertToCsv;
use Panth\ExtraFee\Controller\Adminhtml\OrderFee\Export;
use Panth\ExtraFee\Controller\Adminhtml\Rule\CategoryTree;
use Panth\ExtraFee\Controller\Adminhtml\Rule\ProductsGrid;
use Panth\ExtraFee\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class CatalogLookupTest extends ControllerTestCase
{
    private function scope(string $path, string $suffix): ScopeConfigInterface
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(static fn($p) => $p === $path ? $suffix : null);
        return $scope;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);
        return $manager;
    }

    public function testProductsGridReturnsProductsWithStorefrontUrls(): void
    {
        $collection = $this->collectionOf(ProductCollection::class, [
            new DataObject(['id' => '5', 'name' => 'Shirt', 'sku' => 'S1', 'type_id' => 'simple', 'price' => '9.5',
                'url_key' => 'shirt']),
            new DataObject(['id' => 6, 'name' => 'Hidden URL', 'sku' => 'S2', 'type_id' => 'virtual', 'price' => null]),
        ]);
        foreach (['addAttributeToSelect', 'addAttributeToFilter', 'setPageSize', 'setOrder'] as $method) {
            $collection->method($method)->willReturnSelf();
        }

        (new ProductsGrid(
            $this->buildContext(),
            $this->factoryReturning(ProductCollectionFactory::class, $collection),
            $this->jsonFactory(),
            $this->scope('catalog/seo/product_url_suffix', '.html'),
            $this->storeManager()
        ))->execute();

        $this->assertSame([
            ['id' => 5, 'name' => 'Shirt', 'sku' => 'S1', 'type' => 'simple', 'price' => 9.5,
                'url' => 'https://shop.test/shirt.html'],
            ['id' => 6, 'name' => 'Hidden URL', 'sku' => 'S2', 'type' => 'virtual', 'price' => 0.0, 'url' => ''],
        ], $this->json['products']);
    }

    public function testCategoryTreeNestsChildrenUnderParents(): void
    {
        $collection = $this->collectionOf(CategoryCollection::class, [
            new DataObject(['id' => 2, 'name' => 'Root', 'level' => 1, 'parent_id' => 1, 'url_key' => 'root']),
            new DataObject(['id' => 3, 'name' => 'Men', 'level' => 2, 'parent_id' => 2, 'url_path' => 'root/men']),
            new DataObject(['id' => 4, 'name' => 'Shirts', 'level' => 3, 'parent_id' => 3]),
        ]);
        foreach (['addAttributeToSelect', 'addAttributeToFilter', 'setOrder'] as $method) {
            $collection->method($method)->willReturnSelf();
        }

        (new CategoryTree(
            $this->buildContext(),
            $this->factoryReturning(CategoryCollectionFactory::class, $collection),
            $this->jsonFactory(),
            $this->scope('catalog/seo/category_url_suffix', '/'),
            $this->storeManager()
        ))->execute();

        $tree = $this->json['categories'];
        $this->assertCount(1, $tree);
        $this->assertSame('Root', $tree[0]['name']);
        $this->assertSame('https://shop.test/root/', $tree[0]['url']);
        $this->assertSame('Men', $tree[0]['children'][0]['name']);
        $this->assertSame('https://shop.test/root/men/', $tree[0]['children'][0]['url']);
        $this->assertSame('Shirts', $tree[0]['children'][0]['children'][0]['name']);
        $this->assertSame('', $tree[0]['children'][0]['children'][0]['url']);
    }

    public function testExportStreamsTheCsvFile(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $csv = $this->createStub(ConvertToCsv::class);
        $csv->method('getCsvFile')->willReturn(['type' => 'filename', 'value' => 'export/fees.csv', 'rm' => true]);
        $fileFactory = $this->createMock(FileFactory::class);
        $fileFactory->expects($this->once())->method('create')
            ->with('order_fees.csv', ['type' => 'filename', 'value' => 'export/fees.csv', 'rm' => true], 'var')
            ->willReturn($response);

        $this->assertSame($response, (new Export($this->buildContext(), $csv, $fileFactory))->execute());
    }

    public function testExportErrorsRedirectBackWithAMessage(): void
    {
        $localized = $this->createStub(ConvertToCsv::class);
        $localized->method('getCsvFile')->willThrowException(new LocalizedException(__('Nothing to export.')));
        (new Export($this->buildContext(), $localized, $this->createStub(FileFactory::class)))->execute();
        $this->assertSame(['Nothing to export.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);

        $generic = $this->createStub(ConvertToCsv::class);
        $generic->method('getCsvFile')->willThrowException(new \RuntimeException('io'));
        (new Export($this->buildContext(), $generic, $this->createStub(FileFactory::class)))->execute();
        $this->assertSame(['Something went wrong while exporting order fees.'], $this->messages['exception']);
    }
}
