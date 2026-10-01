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
 * memoised but invalidated on write, and a category listing prefetches Variations into a request cache while
 * leaving the Variations() relation a plain, filterable list.
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

    public function testCategoryListingKeepsVariationsFilterable(): void
    {
        $category = ProductCategory::create(['Title' => 'Eager Cat', 'URLSegment' => 'eager-cat']);
        $category->write();
        $category->publishSingle();

        $product = Product::create([
            'Title' => 'Eager Prod',
            'URLSegment' => 'eager-prod',
            'ParentID' => $category->ID,
            'BasePrice' => 10,
        ]);
        $product->write();
        $product->publishSingle();

        $list = $category->ProductsShowable();
        $this->assertGreaterThan(0, $list->count());

        $first = $list->first();
        $this->assertInstanceOf(Product::class, $first);

        // The prefetch must NOT turn Variations() into a non-filterable EagerLoadedList — that breaks core PriceRange
        // and any custom ->filter()/->sort() on a listing.
        $variations = $first->Variations();
        $this->assertNotInstanceOf(EagerLoadedList::class, $variations);

        // The exact regression this guards: filtering/aggregating the relation must not throw.
        $this->assertSame(0, $variations->filter('Price:GreaterThan', 0)->count());

        // The stock check is still answered (from the prefetch cache), without a per-product query.
        $this->assertFalse($first->hasVariations());
    }

    public function testOverrideProductsShowableKeepsSuppliedListFilterable(): void
    {
        $category = ProductCategory::create(['Title' => 'Override Cat', 'URLSegment' => 'override-cat']);
        $category->write();
        $category->publishSingle();

        $product = Product::create([
            'Title' => 'Override Prod',
            'URLSegment' => 'override-prod',
            'ParentID' => $category->ID,
            'BasePrice' => 10,
        ]);
        $product->write();
        $product->publishSingle();

        // Simulate another module (e.g. silvershop/category-index) supplying an overridden product list.
        $override = Product::get()->filter('ParentID', $category->ID);
        $recursive = true;
        $category->invokeWithExtensions('overrideProductsShowable', $override, $recursive);

        $first = $override->first();
        $this->assertInstanceOf(Product::class, $first);
        $this->assertNotInstanceOf(EagerLoadedList::class, $first->Variations());
        $this->assertSame(0, $first->Variations()->filter('Price:GreaterThan', 0)->count());
    }
}
