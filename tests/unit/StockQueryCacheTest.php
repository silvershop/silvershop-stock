<?php

declare(strict_types=1);

namespace SilverShop\Stock\Tests;

use SilverShop\Page\Product;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers the request-scoped warehouse-stock query cache added to reduce the stock N+1: the records are memoised but
 * invalidated on write, so one availability check no longer fires three queries for the same filter.
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
}
