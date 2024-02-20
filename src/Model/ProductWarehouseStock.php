<?php

namespace SilverShop\Stock\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;
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

    private static $table_name = 'SilverShop_ProductWarehouseStock';

    private static $summary_fields = [
        'Title'             => 'Warehouse',
        'BuyableTitle'      => 'Product',
        'VariationTitle'    => 'Variation',
        'Quantity'
    ];

    /**
     * @var Buyable|null Cache the Buyable record
     */
    protected $_buyable = null;

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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach ($fields->dataFields() as $field) {
            if ($field->Name != 'Quantity') {
                $fields->replaceField($field->Name, $field->performReadonlyTransformation());
            }
        }
        return $fields;
    }

    /**
     * @return string|null
     */
    public function getTitle()
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
    public function getBuyableTitle()
    {

        // This shouldn't happen... but if it does:
        if (!$this->getBuyable()) {
            return _t(__CLASS__ . '.NOTITLE', '<unknown>');
        }

        if ($this->getBuyable()->isVariation()) {
            return $this->getBuyable()->Product()->Title;
        }

        return $this->getBuyable()->Title;
    }

    /**
     * Get the title for the variation
     */
    public function getVariationTitle()
    {
        if ($this->getBuyable() && $this->getBuyable()->isVariation()) {
            return  $this->getBuyable()->Title;
        }
    }
}
