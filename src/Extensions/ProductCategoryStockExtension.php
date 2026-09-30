<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverShop\Extension\ProductVariationsExtension;
use SilverShop\Page\Product;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;

/**
 * Applied to {@link \SilverShop\Page\ProductCategory}. Eager-loads the product Variations relation for a category
 * listing (via the core `updateProductsShowable` hook), so checking availability/stock per product no longer fires
 * a Variations query per product (N+1).
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
        if ($products instanceof DataList && Product::has_extension(ProductVariationsExtension::class)) {
            $products = $products->eagerLoad('Variations');
        }
    }
}
