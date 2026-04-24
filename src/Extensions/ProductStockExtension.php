<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
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

    public function updateCMSFields(FieldList $fields): void
    {
        if ($this->hasVariations()) {
            // it has variations so then we leave the management of the stock
            // level to the variation.
            $fields->addFieldToTab('Root.Stock', new LiteralField(
                'StockManagedVariations',
                '<p>You have variations attached to this product. To manage the stock level ' .
                'click the Stock tab on each of the variations</p>'
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

            foreach ($defaults as $field => $val) {
                $record->{$field} = $val;
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
            ->addWhere([
                'SilverShop_' . $identifier . '_OrderItem.' . $identifier2 . 'ID' => $this->owner->ID,
                'SilverShop_Order.ID != ?' => $cartID,
                'SilverShop_Order.Status' => 'Cart'
            ])
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
            if ($warehouse->Quantity == "-1" || $warehouse->Quantity == -1) {
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
}
