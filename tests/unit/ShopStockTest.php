<?php

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

class ShopStockTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    private function setStockFor($item, $value)
    {
        $warehouse = $this->objFromFixture(ProductWarehouse::class, 'warehouse');
        $data = array(
            'WarehouseID' => $warehouse->ID,
            'ProductID' => $item->ID,
            'ProductClass' => $item->getStockBaseIdentifier()
        );

        $stock = ProductWarehouseStock::get()->filter($data)->first();

        if (!$stock) {
            $stock = new ProductWarehouseStock($data);
        }

        $stock->Quantity = $value;
        $stock->write();
    }

    public function setUp()
    {
        parent::setUp();

        $this->phone = $this->objFromFixture(Product::class, 'phone');
        $this->ball = $this->objFromFixture(Product::class, 'ball');
        $this->ballRedLarge = $this->objFromFixture(Variation::class, 'redlarge');
        $this->ballRedSmall = $this->objFromFixture(Variation::class, 'redsmall');

        $this->mp3 = $this->objFromFixture(Product::class, 'mp3player');
        $this->cup = $this->objFromFixture(Product::class, 'cup');
    }


    public function testCanPurchaseVariation()
    {
        $this->setStockFor($this->ballRedSmall, 1);
        $this->assertTrue($this->ball->canPurchase());

        $this->assertTrue($this->ballRedSmall->canPurchase());
    }

    public function testGetTotalStockInCarts()
    {
        $this->setStockFor($this->phone, 10);

        $order = new Order(array(
            'Status' => 'Cart'
        ));

        $order->write();

        $orderItem = new OrderItem(array(
            'ProductID' => $this->phone->ID,
            'OrderID' => $order->ID,
            'Quantity' => '5'
        ));

        $orderItem->write();
        $this->assertEquals(5, $this->phone->getTotalStockInCarts());

        // test variations
        $this->setStockFor($this->ballRedSmall, 5);

        $orderItem = $orderItem->newClassInstance(VariationOrderItem::class);
        $orderItem->ProductVariationID = $this->ballRedSmall->ID;
        $orderItem->write();

        $this->assertEquals(5, $this->ballRedSmall->getTotalStockInCarts());
    }

    public function testHasAvailableStockProduct()
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

    public function testCanPurchaseNotEnough()
    {
        $this->setStockFor($this->phone, 0);
        $this->assertFalse($this->phone->canPurchase(null, 1));
    }

    public function testGetCmsFields()
    {
        $this->assertInstanceOf(FieldList::class, $this->phone->getCMSFields());
    }
}
