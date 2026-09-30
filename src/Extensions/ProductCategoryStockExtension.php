<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverShop\Extension\ProductVariationsExtension;
use SilverShop\Page\Product;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;

/**
 * Applied to {@link \SilverShop\Page\ProductCategory}. Eager-loads the product Variations relation for a category
 * listing, so checking availability/stock per product doesn't fire a Variations query per product (N+1).
 *
 * It hooks both list paths of {@link \SilverShop\Page\ProductCategory::ProductsShowable()}: the built list
 * (`updateProductsShowable`) and an overridden list supplied by another module such as silvershop/category-index
 * (`overrideProductsShowable`). The override hook is ordered after silvershop-category-index so it post-processes
 * whatever list that module set, and is a no-op when no override was supplied.
 *
 * Variations is a real has_many, so eagerLoad() applies. The warehouse-stock link is polymorphic
 * (ProductID + ProductClass), which SilverStripe 6.2 cannot eager-load, so that stays request-memoised in
 * {@link ProductStockExtension}.
 *
 * @extends Extension<\SilverShop\Page\ProductCategory>
 */
class ProductCategoryStockExtension extends Extension
{
    public function updateProductsShowable(&$products): void
    {
        $products = $this->eagerLoadVariations($products);
    }

    public function overrideProductsShowable(&$override, $recursive = true): void
    {
        $override = $this->eagerLoadVariations($override);
    }

    /**
     * Eager-load Variations onto the list when it is a DataList and the product supports variations; otherwise
     * return it unchanged (so a null/absent override is left alone).
     */
    private function eagerLoadVariations(mixed $list): mixed
    {
        if ($list instanceof DataList && Product::has_extension(ProductVariationsExtension::class)) {
            return $list->eagerLoad('Variations');
        }

        return $list;
    }
}
