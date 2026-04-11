<?php

declare(strict_types=1);

namespace SilverShop\Stock\Model;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\FieldList;

/**
 * A product warehouse contains a quantity of a given stock. When an order is
 * made, the stock value is decreased from the order of the warehouses in the
 * CMS.
 */
class ProductWarehouse extends DataObject
{
    private static string $table_name = 'SilverShop_ProductWarehouse';

    private static array $db = [
        'Title' => 'Varchar(255)'
    ];

    private static array $has_many = [
        'StockedProducts' => ProductWarehouseStock::class
    ];

    #[Override]
    protected function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        foreach ($this->StockedProducts() as $stock) {
            $stock->delete();
        }
    }

    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        if ($stocked = $fields->dataFieldByName('StockedProducts')) {
            $stocked->getConfig()->removeComponentsByType([
                GridFieldAddNewButton::class,
                GridFieldAddExistingAutocompleter::class,
                GridFieldDeleteAction::class
            ]);
        }

        return $fields;
    }
}
