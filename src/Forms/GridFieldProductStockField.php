<?php

namespace SilverShop\Stock\Forms;

use SilverStripe\Forms\GridField\GridField_SaveHandler;
use SilverStripe\Forms\GridField\GridField;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObjectInterface;

/**
 * Handles inline editing / creation of the {@link ProductWarehouseStock}
 *
 */
class GridFieldProductStockField implements GridField_SaveHandler
{

    public function handleSave(GridField $grid, DataObjectInterface $record)
    {
        $data = $grid->Value();

        if (isset($data['GridFieldEditableColumns'])) {
            // go through every warehouse and make sure the have either 0 stock
            // or take the value from this
            $warehouses = ProductWarehouse::get();

            foreach ($warehouses as $warehouse) {
                $stock = $record->getStockForWarehouse($warehouse);

                $quantity = null;

                if (isset($data['GridFieldEditableColumns'][$stock->ID])) {
                    if (isset($data['GridFieldEditableColumns'][$stock->ID]['Quantity'])) {
                        $quantity = (int) $data['GridFieldEditableColumns'][$stock->ID]['Quantity'];
                    }
                }

                $stock->Quantity = $quantity;
                $stock->write();
            }
        }
    }
}
