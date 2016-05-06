<?php

/**
 * @package silvershop-stock
 */
class ProductStockAddProductExtension extends Extension
{

    /**
     * If there is no stock across all products, then disable the add product
     * form.
     *
     * @param mixed
     */
    public function updateAddProductForm($product)
    {
        $data = $this->owner->controller->data();

        if (!$data->hasAvailableStock()) {
            $this->owner->makeReadonly();
        }
    }
}
