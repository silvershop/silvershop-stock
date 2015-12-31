<?php

/**
 * A product warehouse contains a quantity of a given stock. When an order is
 * made, the stock value is decreased from the order of the warehouses in the
 * CMS.
 *
 * @package shop_stock
 */
class ProductWarehouse extends DataObject
{

    private static $db = array(
        'Title' => 'Varchar'
    );

    private static $has_many = array(
        'StockedProducts' => 'ProductWarehouseStock'
    );

    /**
     * Ensure all the stock is removed when we remove the warehouse
     *
     * @return void
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        foreach ($this->StockedProducts() as $stock) {
            $stock->delete();
        }
    }
}
