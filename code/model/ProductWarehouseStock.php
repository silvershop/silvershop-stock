<?php

/**
 * @package silvershop-stock
 */
class ProductWarehouseStock extends DataObject
{

    private static $db = array(
        'Quantity' => 'Int', // needs to support -1 for no limit
        'ProductID' => 'Int',
        'ProductClass' => 'Varchar' // instance of Buyable
    );

    /**
     * Set Quantity to -1 for default unlimited stock.
     * Can be set in your config.yml
     * @var array
     */
    private static $defaults = array(
        'Quantity' => 0
    );

    private static $has_one = array(
        'Warehouse' => 'ProductWarehouse'
    );

    private static $indexes = array(
        'LastEdited' => true,
    );

    public function getTitle()
    {
        return ($warehouse = $this->Warehouse()) ? $warehouse->Title : null;
    }
}
