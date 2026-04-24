<?php

declare(strict_types=1);

namespace SilverShop\Stock\Model;

use Override;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverStripe\Forms\FieldList;
use SilverShop\Model\Buyable;

class ProductWarehouseStock extends DataObject
{
    private static string $table_name = 'SilverShop_ProductWarehouseStock';

    private static array $db = [
        'Quantity' => 'Varchar',
        'ProductID' => 'Int',
        'ProductClass' => 'Varchar(255)' // instance of Buyable
    ];

    private static array $has_one = [
        'Warehouse' => ProductWarehouse::class
    ];

    private static array $summary_fields = [
        'Title'             => 'Warehouse',
        'BuyableTitle'      => 'Product',
        'VariationTitle'    => 'Variation',
        'Quantity'
    ];

    /**
     * @var Buyable|false|null Cache the Buyable record
     */
    protected $_buyable = null;

    /**
     * Set Quantity to -1 for default unlimited stock.
     */
    private static array $defaults = [
        'Quantity' => '-1'
    ];

    private static array $indexes = [
        'LastEdited' => true,
    ];

    #[Override]
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        foreach ($fields->dataFields() as $field) {
            if ($field->Name !== 'Quantity') {
                $fields->replaceField($field->Name, $field->performReadonlyTransformation());
            }
        }
        return $fields;
    }

    public function getTitle(): ?string
    {
        return ($warehouse = $this->Warehouse()) ? $warehouse->Title : null;
    }

    public function getBuyable()
    {
        if (is_null($this->_buyable)) {
            if (!$this->ProductClass || !$this->ProductID) {
                $buyable = false;
            } else {
                $class = Injector::inst()->get($this->ProductClass, true);
                $buyable = $class::get()->byID($this->ProductID);
                if (!$buyable || !$buyable->exists()) {
                    $buyable = false;
                }
            }
            $this->_buyable = $buyable;
        }

        return $this->_buyable;
    }

    /**
     * Get the title for the buyable (product)
     */
    public function getBuyableTitle(): string
    {
        // This shouldn't happen... but if it does:
        if (!$this->getBuyable()) {
            return _t(__CLASS__ . '.NOTITLE', '<unknown>');
        }

        if (method_exists($this->getBuyable(), 'isVariation') && $this->getBuyable()->isVariation()) {
            return $this->getBuyable()->Product()->Title;
        }

        return $this->getBuyable()->Title ?? '';
    }

    /**
     * Get the title for the variation
     */
    public function getVariationTitle(): ?string
    {
        if ($this->getBuyable() && method_exists($this->getBuyable(), 'isVariation') && $this->getBuyable()->isVariation()) {
            return $this->getBuyable()->Title;
        }
        return null;
    }
}
