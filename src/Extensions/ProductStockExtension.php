<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\CMS\Model\SiteTree;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Forms\GridFieldProductStockField;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Model\Order;
use SilverShop\Model\Variation\Variation;
use SilverShop\Model\OrderItem;
use SilverStripe\Core\Config\Configurable;

/**
 * An extension which can be applied to either the shop {@link Product} or
 * {@link Variation} class for including stock values in the CMS.
 *
 * Stock is held within a {@link ProductWarehouse}.
 */
class ProductStockExtension extends Extension
{
    use Configurable;
    
    private static bool $allow_out_of_stock_purchase = false;

    /**
     * Only count carts edited within this many minutes as reserving stock (see getTotalStockInCarts()).
     * 0 (default) = count every Cart order, i.e. no behaviour change. Set it so abandoned/bot carts do
     * not reserve stock indefinitely.
     */
    private static int $pending_cart_max_age_mins = 0;

    public function updateCMSFields(FieldList $fields): void
    {
        if ($this->hasVariations()) {
            // it has variations so then we leave the management of the stock
            // level to the variation.
            $fields->addFieldToTab('Root.Stock', new LiteralField(
                'StockManagedVariations',
                '<p class="message notice" style="display:flex;align-items:flex-start;gap:.5em">'
                . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>' . _t(
                    __CLASS__ . '.StockManagedVariations',
                    'This product has variations. Set each variation\'s stock inline in the "Stock" column on the '
                    . '"Variations" tab, or open a variation to edit it there.'
                ) . '</span></p>'
            ));

            return;
        }

        $grid = new GridField(
            'StockLevels',
            _t(__CLASS__ . '.Stock', 'Stock'),
            $this->getStockForEachWarehouse(),
            GridFieldConfig::create()
                ->addComponent(new GridFieldButtonRow('before'))
                ->addComponent(new GridFieldToolbarHeader())
                ->addComponent(new GridFieldEditableColumns())
                ->addComponent(new GridFieldProductStockField())
        );

        $grid->getConfig()->getComponentByType(GridFieldEditableColumns::class)->setDisplayFields([
            'Title' => [
                'field' => ReadonlyField::class
            ],
            'Quantity'  => function ($record, $column, $grid) {
                return new TextField($column);
            }
        ]);

        if ($fields->fieldByName('Root')) {
            $fields->addFieldToTab('Root.Stock', $grid);
        } else {
            $fields->push($grid);
        }
    }

    /**
     * Contribute an inline-editable "Stock" column to the product's Variations grid
     * (core fires this via extend() when building that grid). Only added when at least
     * one warehouse exists; assumes a single warehouse — true for virtually every shop —
     * and edits that warehouse's level. Keyed to Variation::getStockLevel()/setStockLevel().
     */
    public function updateVariationEditableColumns(array &$displayFields): void
    {
        if (!ProductWarehouse::get()->exists()) {
            return;
        }

        $displayFields['StockLevel'] = [
            'title' => _t(__CLASS__ . '.StockColumn', 'Stock'),
            'callback' => fn ($record, $column, $grid): NumericField =>
                NumericField::create($column)->setHTML5(true)->setScale(0)
                    ->setAttribute('style', 'width:6em'),
        ];

        // Opt-in: an explicit "Unlimited" checkbox instead of the -1 sentinel.
        if (ProductWarehouseStock::config()->get('use_unlimited_checkbox')) {
            $displayFields['StockUnlimited'] = [
                'title' => _t(__CLASS__ . '.UnlimitedColumn', 'Unlimited'),
                'callback' => fn ($record, $column, $grid): CheckboxField =>
                    CheckboxField::create($column),
            ];
        }
    }

    /**
     * Single-warehouse stock quantity for this buyable, for the inline grid column.
     * Null when no stock record exists yet; "-1" means unlimited.
     */
    public function getStockLevel(): ?string
    {
        $warehouse = ProductWarehouse::get()->first();
        if (!$warehouse) {
            return null;
        }

        $record = ProductWarehouseStock::get()->filter([
            'ProductID' => $this->owner->ID,
            'ProductClass' => $this->owner->ClassName,
            'WarehouseID' => $warehouse->ID,
        ])->first();

        return $record ? (string) $record->Quantity : null;
    }

    /**
     * Persist the single-warehouse stock quantity edited inline in the grid.
     */
    public function setStockLevel($value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $warehouse = ProductWarehouse::get()->first();
        if (!$warehouse) {
            return;
        }

        if ($record = $this->getStockForWarehouse($warehouse)) {
            $record->Quantity = (string) (int) $value;
            $record->write();
        }
    }

    /**
     * Single-warehouse "Unlimited" flag for the inline grid column (only shown when
     * ProductWarehouseStock.use_unlimited_checkbox is enabled).
     */
    public function getStockUnlimited(): bool
    {
        $warehouse = ProductWarehouse::get()->first();
        if (!$warehouse) {
            return false;
        }

        $record = ProductWarehouseStock::get()->filter([
            'ProductID' => $this->owner->ID,
            'ProductClass' => $this->owner->ClassName,
            'WarehouseID' => $warehouse->ID,
        ])->first();

        return $record ? (bool) $record->Unlimited : false;
    }

    public function setStockUnlimited($value): void
    {
        $warehouse = ProductWarehouse::get()->first();
        if (!$warehouse) {
            return;
        }

        if ($record = $this->getStockForWarehouse($warehouse)) {
            $record->Unlimited = (bool) $value;
            $record->write();
        }
    }

    private function warehouseStockIsUnlimited(ProductWarehouseStock $stock): bool
    {
        if (ProductWarehouseStock::config()->get('use_unlimited_checkbox')) {
            return (bool) $stock->Unlimited;
        }

        return ($stock->Quantity == '-1' || $stock->Quantity == -1);
    }

    public function getStockForEachWarehouse(): ArrayList
    {
        $warehouses = ProductWarehouse::get();
        $output = new ArrayList();

        foreach ($warehouses as $warehouse) {
            if ($stock = $this->getStockForWarehouse($warehouse)) {
                $output->push($stock);
            }
        }

        return $output;
    }

    public function getStockForWarehouse(ProductWarehouse $warehouse, bool $strictCreate = true): ?ProductWarehouseStock
    {
        /** @var ProductWarehouseStock|null $record */
        $record = $warehouse->StockedProducts()->filter([
           'ProductID'=> $this->owner->ID,
           'ProductClass'=>$this->owner->ClassName
        ])->first();

        if (!$record && ($this->owner->isInDB() || !$strictCreate)) {
            $defaults = ProductWarehouseStock::config()->get('defaults');
            $record = Injector::inst()->create(ProductWarehouseStock::class);
            $record->WarehouseID = $warehouse->ID;
            $record->ProductID = $this->owner->ID;
            $record->ProductClass = $this->owner->ClassName;
            $record->Quantity = '0';

            if (ProductWarehouseStock::config()->get('use_unlimited_checkbox')) {
                // Unlimited by default via the explicit flag; Quantity stays a real number.
                $record->Unlimited = true;
            } else {
                // Legacy: the -1 sentinel from $defaults marks unlimited.
                foreach ($defaults as $field => $val) {
                    $record->{$field} = $val;
                }
            }

            $record->write();
        }

        return $record;
    }

    public function hasAvailableStock(int $require = 1): bool
    {
        if ($this->hasVariations()) {
            foreach ($this->owner->Variations() as $variation) {
                if ($variation->hasAvailableStock($require)) {
                    return true;
                }
            }
        }

        if ($this->hasWarehouseWithUnlimitedStock()) {
            return true;
        } else {
            $stock = (int) $this->getWarehouseStockQuantity();
            $pending = (int) $this->getTotalStockInCarts();

            return ($stock - $pending) >= $require;
        }
    }

    public function getTotalStockInCarts(): int
    {
        $current = ShoppingCart::curr();

        $cartID = 0;
        if ($current) {
            $cartID = $current->ID;
        }

        if ($this->owner instanceof Variation) {
            $identifier = "Variation";
            $identifier2 = "ProductVariation";
        } else {
            $identifier = "Product";
            $identifier2 = "Product";
        }

        $where = [
            'SilverShop_' . $identifier . '_OrderItem.' . $identifier2 . 'ID' => $this->owner->ID,
            'SilverShop_Order.ID != ?' => $cartID,
            'SilverShop_Order.Status' => 'Cart'
        ];

        // Optionally ignore stale/abandoned carts so they don't reserve stock indefinitely
        // (0 = count every Cart order, i.e. no behaviour change).
        $maxAgeMins = (int) self::config()->get('pending_cart_max_age_mins');
        if ($maxAgeMins > 0) {
            $where['SilverShop_Order.LastEdited >= ?'] = date('Y-m-d H:i:s', strtotime("-{$maxAgeMins} minutes"));
        }

        // Build the SQL query using SQLSelect
        $query = SQLSelect::create()
            ->setSelect([
                'SUM(SilverShop_OrderItem.Quantity) AS QuantitySum'
            ])
            ->setFrom('SilverShop_OrderItem')
            ->addLeftJoin(
                'SilverShop_' . $identifier . '_OrderItem',
                'SilverShop_' . $identifier . '_OrderItem.ID = SilverShop_OrderItem.ID'
            )
            ->addLeftJoin(
                'SilverShop_OrderAttribute',
                'SilverShop_OrderAttribute.ID = SilverShop_OrderItem.ID'
            )
            ->addLeftJoin(
                'SilverShop_Order',
                'SilverShop_Order.ID = SilverShop_OrderAttribute.OrderID'
            )
            ->addWhere($where)
            ->addGroupBy('SilverShop_' . $identifier . '_OrderItem.' . $identifier2 . 'ID');

        $result = $query->execute()->record();

        if ($result && isset($result['QuantitySum'])) {
            $quantitySum = $result['QuantitySum'];
        } else {
            $quantitySum = 0;
        }

        return (int) $quantitySum;
    }

    public function hasWarehouseWithUnlimitedStock(): bool
    {
        if (ProductWarehouseStock::config()->get('use_unlimited_checkbox')) {
            return ($this->getWarehouseStock()->filter('Unlimited', true)->count() > 0);
        }

        return ($this->getWarehouseStock()->where("\"Quantity\" = '-1'")->count() > 0);
    }

    public function getWarehouseStock()
    {
        return ProductWarehouseStock::get()->filter([
            'ProductID' => $this->owner->ID,
            'ProductClass' => $this->owner->getClassName()
        ]);
    }

    public function getWarehouseStockQuantity(): int
    {
        return (int) $this->getWarehouseStock()->sum('Quantity');
    }

    public function canPurchase($member = null, int $quantity = 1): bool
    {
        if ($this->getWarehouseStock()->count() < 1) {
            return true;
        }

        if ($this->hasVariations()) {
            return true;
        } else {
            $outOfStockAllowed = self::config()->get('allow_out_of_stock_purchase');

            if ($outOfStockAllowed) {
                return true;
            }

            if (!$this->hasAvailableStock($quantity)) {
                return false;
            }

            return true;
        }
    }

    public function hasVariations(): bool
    {
        $schema = $this->owner->getSchema();
        $componentClass = $schema->hasManyComponent($this->owner->ClassName, 'Variations');

        return ($componentClass && $this->owner->Variations()->exists());
    }

    public function isVariation(): bool
    {
        return ($this->owner instanceof Variation);
    }

    public function getStockBaseIdentifier(): string
    {
        return $this->owner->getClassName();
    }

    public function decrementStock(OrderItem $orderItem)
    {
        $quantity = (int) $orderItem->Quantity;

        foreach ($this->getWarehouseStock() as $warehouse) {
            if ($this->warehouseStockIsUnlimited($warehouse)) {
                break;
            }

            $currentQTY = (int) $warehouse->Quantity;

            if ($quantity <= $currentQTY) {
                $warehouse->Quantity = (string) ($currentQTY - $quantity);
            } else {
                $quantity = $quantity - $currentQTY;
                $warehouse->Quantity = '0';
            }

            $warehouse->write();
        }

        return $this->owner;
    }

    public function incrementStock(OrderItem $orderItem)
    {
        $quantity = (int) $orderItem->Quantity;

        foreach ($this->getWarehouseStock() as $warehouse) {
            if ($this->warehouseStockIsUnlimited($warehouse)) {
                continue;
            }

            $warehouse->Quantity = (string) ((int) $warehouse->Quantity + $quantity);
            $warehouse->write();
        }

        return $this->owner;
    }
}
