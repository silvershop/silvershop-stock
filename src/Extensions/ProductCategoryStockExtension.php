<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverShop\Extension\ProductVariationsExtension;
use SilverShop\Page\Product;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;

/**
 * Applied to {@link \SilverShop\Page\ProductCategory}. Prefetches the product Variations for a category listing into a
 * request-scoped cache ({@link ProductStockExtension}), so checking availability/stock per product doesn't fire a
 * Variations query per product (N+1).
 *
 * It does NOT eager-load the relation: `$product->Variations()` stays a plain, filterable {@link DataList}, so any
 * code (core PriceRange, custom filters in themes/modules) can still `->filter()/->sort()` it. Instead it loads every
 * listed product's variations in one query up front and the stock checks read from that cache.
 *
 * It hooks both list paths of {@link \SilverShop\Page\ProductCategory::ProductsShowable()}: the built list
 * (`updateProductsShowable`) and an overridden list supplied by another module such as silvershop/category-index
 * (`overrideProductsShowable`). The override hook is ordered after silvershop-category-index so it post-processes
 * whatever list that module set, and is a no-op when no override was supplied.
 *
 * The warehouse-stock link is polymorphic (ProductID + ProductClass), which SilverStripe 6.2 cannot eager-load, so
 * that stays request-memoised in {@link ProductStockExtension}.
 *
 * @extends Extension<\SilverShop\Page\ProductCategory>
 */
class ProductCategoryStockExtension extends Extension
{
    public function updateProductsShowable(&$products): void
    {
        $this->prefetchVariations($products);
    }

    public function overrideProductsShowable(&$override, $recursive = true): void
    {
        $this->prefetchVariations($override);
    }

    /**
     * Load the Variations of every product in the list in one query into the request-scoped cache, keyed by product.
     * No-op when the list is not a DataList (e.g. a null/absent override) or products don't support variations. The
     * list itself is left untouched — its products' Variations() relation stays a normal filterable DataList.
     */
    private function prefetchVariations(mixed $list): void
    {
        if (!($list instanceof DataList) || !Product::has_extension(ProductVariationsExtension::class)) {
            return;
        }

        // [productID => ClassName] for the listed products (one lightweight query).
        $classById = $list->map('ID', 'ClassName')->toArray();
        if ($classById) {
            ProductStockExtension::primeVariationsCache($classById);
        }
    }
}
