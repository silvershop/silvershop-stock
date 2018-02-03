<?php

namespace SilverShop\Stock\Model;

use SilverStripe\ORM\DataObject;
use SilverShop\Stock\Model\ProductWarehouse;

class ProductWarehouseStock extends DataObject
{
    private static $db = [
        'Quantity' => 'Varchar',
        'ProductID' => 'Int',
        'ProductClass' => 'Varchar(255)' // instance of Buyable
    ];

    private static $has_one = [
        'Warehouse' => ProductWarehouse::class
    ];

    private static $table_name = 'ProductWarehouseStock';

    /**
     * Set Quantity to -1 for default unlimited stock.
     *
     * @var array
     */
    private static $defaults = [
        'Quantity' => '-1'
    ];

    private static $indexes = [
        'LastEdited' => true,
    ];

    /**
     * @return string|null
     */
    public function getTitle()
    {
        return ($warehouse = $this->Warehouse()) ? $warehouse->Title : null;
    }
}
