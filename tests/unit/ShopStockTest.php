<?php

declare(strict_types=1);

namespace SilverShop\Stock\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverShop\Model\Order;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Model\Product\OrderItem;
use SilverShop\Page\Product;
use SilverStripe\Forms\FieldList;
use SilverShop\Model\Variation\Variation;
use SilverShop\Model\Variation\OrderItem as VariationOrderItem;
use Override;

class ShopStockTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    protected Product $phone;
    protected Product $ball;
    protected Variation $ballRedLarge;
    protected Variation $ballRedSmall;
    protected Product $mp3;
    protected Product $cup;

    private function setStockFor($item, $value): void
    {
        $warehouse = $this->objFromFixture(ProductWarehouse::class, 'warehouse');
        $data = [
            'WarehouseID' => $warehouse->ID,
            'ProductID' => $item->ID,
            'ProductClass' => $item->getStockBaseIdentifier()
        ];

        $stock = ProductWarehouseStock::get()->filter($data)->first();

        if (!$stock) {
            $stock = ProductWarehouseStock::create($data);
        }

        $stock->Quantity = (string) $value;
        $stock->write();
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->phone = $this->objFromFixture(Product::class, 'phone');
        $this->ball = $this->objFromFixture(Product::class, 'ball');
        $this->ballRedLarge = $this->objFromFixture(Variation::class, 'redlarge');
        $this->ballRedSmall = $this->objFromFixture(Variation::class, 'redsmall');

        $this->mp3 = $this->objFromFixture(Product::class, 'mp3player');
        $this->cup = $this->objFromFixture(Product::class, 'cup');
    }

    public function testCanPurchaseVariation(): void
    {
        $this->setStockFor($this->ballRedSmall, 1);
        $this->assertTrue($this->ball->canPurchase());

        $this->assertTrue($this->ballRedSmall->canPurchase());
    }

    public function testGetTotalStockInCarts(): void
    {
        $this->setStockFor($this->phone, 10);

        $order = Order::create([
            'Status' => 'Cart'
        ]);

        $order->write();

        $orderItem = OrderItem::create([
            'ProductID' => $this->phone->ID,
            'OrderID' => $order->ID,
            'Quantity' => 5
        ]);

        $orderItem->write();
        $this->assertEquals(5, $this->phone->getTotalStockInCarts());

        // test variations
        $this->setStockFor($this->ballRedSmall, 5);

        /** @var VariationOrderItem $orderItem */
        $orderItem = $orderItem->newClassInstance(VariationOrderItem::class);
        $orderItem->ProductVariationID = $this->ballRedSmall->ID;
        $orderItem->write();

        $this->assertEquals(5, $this->ballRedSmall->getTotalStockInCarts());
    }

    public function testHasAvailableStockProduct(): void
    {
        // no stock defined so no. Stock must be opt in
        $this->assertFalse($this->cup->hasAvailableStock());
        $this->setStockFor($this->phone, 10);
        $this->assertTrue($this->phone->hasAvailableStock(), 'Phone should have 10 stock');
        $this->assertFalse($this->phone->hasAvailableStock(15), 'Phone should only have 10 in stock');

        // on products will variations, look across variation.
        $this->setStockFor($this->ballRedLarge, 0);
        $this->setStockFor($this->ballRedSmall, 1);

        $this->assertTrue($this->ball->hasAvailableStock());
    }

    public function testCanPurchaseNotEnough(): void
    {
        $this->setStockFor($this->phone, 0);
        $this->assertFalse($this->phone->canPurchase(null, 1));
    }

    public function testGetCmsFields(): void
    {
        $this->assertInstanceOf(FieldList::class, $this->phone->getCMSFields());
    }

    public function testIncrementStockOnAdminCancellation(): void
    {
        $this->setStockFor($this->phone, 5);

        $order = Order::create(['Status' => 'Paid']);
        $order->write();

        $orderItem = OrderItem::create([
            'ProductID' => $this->phone->ID,
            'OrderID' => $order->ID,
            'Quantity' => 3,
        ]);
        $orderItem->write();

        // Simulate stock decrement on placement
        $this->phone->decrementStock($orderItem);
        $this->assertEquals(2, $this->phone->getWarehouseStockQuantity());

        // Cancel the order as admin
        $order->Status = 'AdminCancelled';
        $order->write();

        // Stock should be restored
        $this->assertEquals(5, $this->phone->getWarehouseStockQuantity());
    }

    public function testIncrementStockOnMemberCancellation(): void
    {
        $this->setStockFor($this->phone, 8);

        $order = Order::create(['Status' => 'Unpaid']);
        $order->write();

        $orderItem = OrderItem::create([
            'ProductID' => $this->phone->ID,
            'OrderID' => $order->ID,
            'Quantity' => 4,
        ]);
        $orderItem->write();

        // Simulate stock decrement on placement
        $this->phone->decrementStock($orderItem);
        $this->assertEquals(4, $this->phone->getWarehouseStockQuantity());

        // Cancel the order as member
        $order->Status = 'MemberCancelled';
        $order->write();

        // Stock should be restored
        $this->assertEquals(8, $this->phone->getWarehouseStockQuantity());
    }

    public function testIncrementStockOnVariationCancellation(): void
    {
        $this->setStockFor($this->ballRedSmall, 10);

        $order = Order::create(['Status' => 'Paid']);
        $order->write();

        /** @var \SilverShop\Model\Variation\OrderItem $variationOrderItem */
        $variationOrderItem = \SilverShop\Model\Variation\OrderItem::create([
            'ProductID' => $this->ball->ID,
            'ProductVariationID' => $this->ballRedSmall->ID,
            'OrderID' => $order->ID,
            'Quantity' => 3,
        ]);
        $variationOrderItem->write();

        // Simulate stock decrement on placement
        $this->ballRedSmall->decrementStock($variationOrderItem);
        $this->assertEquals(7, $this->ballRedSmall->getWarehouseStockQuantity());

        // Cancel the order as admin
        $order->Status = 'AdminCancelled';
        $order->write();

        // Stock should be restored
        $this->assertEquals(10, $this->ballRedSmall->getWarehouseStockQuantity());
    }

    public function testStockNotReleasedOnOtherStatusChanges(): void
    {
        $this->setStockFor($this->phone, 5);

        $order = Order::create(['Status' => 'Unpaid']);
        $order->write();

        $orderItem = OrderItem::create([
            'ProductID' => $this->phone->ID,
            'OrderID' => $order->ID,
            'Quantity' => 2,
        ]);
        $orderItem->write();

        $this->phone->decrementStock($orderItem);
        $this->assertEquals(3, $this->phone->getWarehouseStockQuantity());

        // Transition to Paid - stock should not be released
        $order->Status = 'Paid';
        $order->write();

        $this->assertEquals(3, $this->phone->getWarehouseStockQuantity());
    }
}
