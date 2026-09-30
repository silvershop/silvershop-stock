<?php

declare(strict_types=1);

namespace SilverShop\Stock\Tests;

use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\EagerLoadedList;

/**
 * Covers the request-scoped query caching added to reduce the stock N+1: the warehouse-stock records are
 * memoised but invalidated on write, and a category listing eager-loads Variations.
 */
class StockQueryCacheTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    private function setStockFor(Product $product, int $qty): void
    {
        $warehouse = $this->objFromFixture(ProductWarehouse::class, 'warehouse');
        $data = [
            'WarehouseID' => $warehouse->ID,
            'ProductID' => $product->ID,
            'ProductClass' => $product->getStockBaseIdentifier(),
        ];
        $stock = ProductWarehouseStock::get()->filter($data)->first() ?: ProductWarehouseStock::create($data);
        $stock->Quantity = (string) $qty;
        $stock->write();
    }

    public function testWarehouseStockRecordsAreCachedButInvalidateOnWrite(): void
    {
        $phone = $this->objFromFixture(Product::class, 'phone');

        $this->setStockFor($phone, 10);
        // Prime the request-scoped cache.
        $this->assertSame(10, $phone->getWarehouseStockQuantity());
        $this->assertCount(1, $phone->getWarehouseStockRecords());

        // A stock write must invalidate the cache so the next read is fresh, not the memoised value.
        $this->setStockFor($phone, 3);
        $this->assertSame(3, $phone->getWarehouseStockQuantity());
    }

    public function testCategoryListingEagerLoadsVariations(): void
    {
        $category = ProductCategory::create(['Title' => 'Eager Cat', 'URLSegment' => 'eager-cat']);
        $category->write();

        $product = Product::create([
            'Title' => 'Eager Prod',
            'URLSegment' => 'eager-prod',
            'ParentID' => $category->ID,
            'BasePrice' => 10,
        ]);
        $product->write();

        $list = $category->ProductsShowable();
        $this->assertGreaterThan(0, $list->count());

        $first = $list->first();
        $this->assertInstanceOf(Product::class, $first);
        $this->assertInstanceOf(
            EagerLoadedList::class,
            $first->Variations(),
            'ProductCategory listing should eager-load the Variations relation to avoid a per-product query'
        );
    }
}
