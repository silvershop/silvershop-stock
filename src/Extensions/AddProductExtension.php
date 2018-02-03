<?php

namespace SilverShop\Stock\Extensions;

use SilverStripe\Core\Extension;

class AddProductExtension extends Extension
{
    /**
     * If there is no stock across all products, then disable the add product
     * form.
     *
     * @param Buyable $product
     */
    public function updateAddProductForm($product)
    {
        $data = $this->owner->controller->data();

        if (!$data->hasAvailableStock()) {
            $this->owner->makeReadonly();
        }
    }
}
