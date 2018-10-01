<?php

namespace SilverShop\Stock\Model;

use SilverStripe\ORM\DataObject;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * A product warehouse contains a quantity of a given stock. When an order is
 * made, the stock value is decreased from the order of the warehouses in the
 * CMS.
 */
class ProductWarehouse extends DataObject
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_many = [
        'StockedProducts' => ProductWarehouseStock::class
    ];

    private static $table_name = 'SilverShop_ProductWarehouse';

    /**
     * Ensure all the stock is removed when we remove the warehouse.
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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if ($stocked = $fields->dataFieldByName('StockedProducts')) {
            $stocked->getConfig()->removeComponentsByType(
                [
                    GridFieldAddNewButton::class,
                    GridFieldAddExistingAutocompleter::class,
                    GridFieldDeleteAction::class
                ]
            );
        }

        return $fields;
    }
}
