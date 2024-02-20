<?php

namespace SilverShop\Stock\Extensions;

use SilverShop\Model\Buyable;
use SilverStripe\Core\Extension;

class AddProductExtension extends Extension
{
    /**
     * If there is no stock across all products, then disable the add product
     * form.
     *
     * @param Buyable $product
     */
    public function updateAddProductForm(?Buyable $product = null)
    {
        $data = $this->owner->controller->data();

        if (!$data->canPurchase()) {
            $this->owner->makeReadonly();
        }
    }
}
