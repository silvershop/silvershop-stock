<?php

/**
 * Handles inline editing / creation of the {@link ProductWarehouseStock}
 *
 * @package silvershop-stock
 */
class GridFieldProductStockFields implements GridField_SaveHandler
{

    public function handleSave(GridField $grid, DataObjectInterface $record)
    {
        $data = $grid->Value();
        $base = ClassInfo::baseDataClass($record);

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
