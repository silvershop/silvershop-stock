<?php

/**
 * @package shop_stock
 * @subpackage tests
 */
class ShopStockTest extends SapphireTest {
	
	protected static $fixture_file = 'shop_stock/tests/fixtures.yml';

	private function setStockFor($item, $value) {
		$warehouse = $this->objFromFixture('ProductWarehouse', 'warehouse');
		$data = array(
			'WarehouseID' => $warehouse->ID,
			'ProductID' => $item->ID,
			'ProductClass' => $item->getStockBaseIdentifier()
		);

		$stock = ProductWarehouseStock::get()->filter($data)->first();

		if(!$stock) {
			$stock = new ProductWarehouseStock($data);
		}

		$stock->Quantity = $value;
		$stock->write();
	}

	public function setUp() {
		parent::setUp();

		$this->phone = $this->objFromFixture('Product', 'phone');
		$this->ball = $this->objFromFixture('Product', 'ball');
		$this->ballRedLarge = $this->objFromFixture('ProductVariation', 'redlarge');
		$this->ballRedSmall = $this->objFromFixture('ProductVariation', 'redsmall');

		$this->mp3 = $this->objFromFixture('Product', 'mp3player');
		$this->cup = $this->objFromFixture('Product', 'cup');
	}


	public function testCanPurchaseVariation() {
		$this->setStockFor($this->ballRedSmall, 1);
		$this->assertTrue($this->ball->canPurchase());

		$this->assertTrue($this->ballRedSmall->canPurchase());
	}

	public function testGetTotalStockInCarts() {
		$this->setStockFor($this->phone, 10);

		$order = new Order(array(
			'Status' => 'Cart'
		));

		$order->write();
		
		$orderItem = new Product_OrderItem(array(
			'ProductID' => $this->phone->ID,
			'OrderID' => $order->ID,
			'Quantity' => '5'
		));

		$orderItem->write();
		$this->assertEquals(5, $this->phone->getTotalStockInCarts());

		// test variations
		$this->setStockFor($this->ballRedSmall, 5);

		$orderItem = $orderItem->newClassInstance('ProductVariation_OrderItem');
		$orderItem->ProductVariationID = $this->ballRedSmall->ID;
		$orderItem->write();

		$this->assertEquals(5, $this->ballRedSmall->getTotalStockInCarts());
	}


	public function testHasAvailableStockProduct() {
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

	public function testCanPurchaseNotEnough() {
		$this->setExpectedException('BuyableNotEnoughStockException');
		$this->setStockFor($this->phone, 0);
		$this->assertFalse($this->phone->canPurchase(null, 1));
	}

	public function testGetCmsFields() {
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
	}
}