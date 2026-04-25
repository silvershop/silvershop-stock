<?php

declare(strict_types=1);

namespace SilverShop\Stock\Forms;

use SilverStripe\Forms\GridField\GridField_SaveHandler;
use SilverStripe\Forms\GridField\GridField;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverStripe\ORM\DataObjectInterface;

/**
 * Handles inline editing / creation of the {@link ProductWarehouseStock}
 */
class GridFieldProductStockField implements GridField_SaveHandler
{
    public function handleSave(GridField $grid, DataObjectInterface $record): void
    {
        $data = $grid->getValue();

        if (isset($data['GridFieldEditableColumns'])) {
            // go through every warehouse and make sure the have either 0 stock
            // or take the value from this
            $warehouses = ProductWarehouse::get();

            foreach ($warehouses as $warehouse) {
                if (method_exists($record, 'getStockForWarehouse')) {
                    $stock = $record->getStockForWarehouse($warehouse);

                    $quantity = '0';

                    if (isset($data['GridFieldEditableColumns'][$stock->ID])) {
                        if (isset($data['GridFieldEditableColumns'][$stock->ID]['Quantity'])) {
                            $quantity = (string) $data['GridFieldEditableColumns'][$stock->ID]['Quantity'];
                        }
                    }

                    $stock->Quantity = $quantity;
                    $stock->write();
                }
            }
        }
    }
}
