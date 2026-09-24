<?php

declare(strict_types=1);

namespace SilverShop\Stock\Model;

use Override;
use SilverStripe\ORM\DataObject;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverStripe\Forms\FieldList;
use SilverShop\Model\Buyable;
use SilverShop\Model\Variation\Variation;

class ProductWarehouseStock extends DataObject
{
    private static string $table_name = 'SilverShop_ProductWarehouseStock';

    /**
     * Opt-in. When true, an explicit "Unlimited" boolean marks unlimited stock instead of the
     * legacy sentinel `Quantity == -1`. Off by default so existing installs are unaffected —
     * where -1 may be a real (negative) quantity, or is already the established unlimited
     * convention. Enable per project:
     *
     *   SilverShop\Stock\Model\ProductWarehouseStock:
     *     use_unlimited_checkbox: true
     *
     * NOTE: enabling on an install that already uses -1 for "unlimited" requires migrating those
     * records to `Unlimited = 1` (otherwise -1 becomes a real quantity and reads as out of stock).
     */
    private static bool $use_unlimited_checkbox = false;

    private static array $db = [
        'Quantity' => 'Varchar',
        'ProductID' => 'Int',
        'ProductClass' => 'Varchar(255)', // instance of Buyable
        'Unlimited' => 'Boolean' // only used when use_unlimited_checkbox is enabled
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
     * @var (DataObject&Buyable)|false|null Cache the Buyable record
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
        // The hot lookup {@see ProductStockExtension::getWarehouseStock()} filters by
        // (ProductID, ProductClass); index it so stock checks don't table-scan on large catalogues.
        'ProductStock' => [
            'type' => 'index',
            'columns' => ['ProductID', 'ProductClass'],
        ],
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
        $warehouse = $this->Warehouse();

        return $warehouse->exists() ? $warehouse->Title : null;
    }

    /**
     * @return (DataObject&Buyable)|false
     */
    public function getBuyable(): DataObject|false
    {
        if (is_null($this->_buyable)) {
            if (!$this->ProductClass || !$this->ProductID) {
                $buyable = false;
            } else {
                $buyable = DataObject::get_by_id($this->ProductClass, (int) $this->ProductID);
                if (!$buyable || !$buyable->exists() || !$buyable instanceof Buyable) {
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
        $buyable = $this->getBuyable();

        // This shouldn't happen... but if it does:
        if (!$buyable) {
            return _t(__CLASS__ . '.NOTITLE', '<unknown>');
        }

        if ($buyable instanceof Variation) {
            $product = $buyable->Product();

            return $product->exists() ? ($product->Title ?? '') : '';
        }

        return $buyable->Title ?? '';
    }

    /**
     * Get the title for the variation
     */
    public function getVariationTitle(): ?string
    {
        $buyable = $this->getBuyable();

        return $buyable instanceof Variation ? $buyable->Title : null;
    }
}
