<?php

declare(strict_types=1);

namespace SilverShop\Stock\Tests;

use Override;
use SilverShop\Model\Product\OrderItem;
use SilverShop\Page\Product;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

/**
 * Covers the inline variation stock column accessors and the opt-in
 * use_unlimited_checkbox behaviour added by ProductStockExtension.
 */
class UnlimitedCheckboxTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    protected Product $phone;

    private function enableCheckbox(): void
    {
        Config::modify()->set(ProductWarehouseStock::class, 'use_unlimited_checkbox', true);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->phone = $this->objFromFixture(Product::class, 'phone');
    }

    public function testStockLevelAccessorsRoundTrip(): void
    {
        $this->assertNull($this->phone->getStockLevel(), 'no stock record yet');

        $this->phone->setStockLevel('7');
        $this->assertSame('7', $this->phone->getStockLevel());

        $this->phone->setStockLevel('-1');
        $this->assertSame('-1', $this->phone->getStockLevel());
    }

    public function testMinusOneMeansUnlimitedWhenCheckboxDisabled(): void
    {
        // Default behaviour: -1 is the unlimited sentinel.
        $this->phone->setStockLevel('-1');
        $this->assertTrue((bool) $this->phone->hasWarehouseWithUnlimitedStock());

        $this->phone->setStockLevel('5');
        $this->assertFalse((bool) $this->phone->hasWarehouseWithUnlimitedStock());
    }

    public function testUnlimitedAccessorsRoundTripWhenCheckboxEnabled(): void
    {
        $this->enableCheckbox();

        $this->phone->setStockUnlimited(true);
        $this->assertTrue($this->phone->getStockUnlimited());
        $this->assertTrue((bool) $this->phone->hasWarehouseWithUnlimitedStock());

        $this->phone->setStockUnlimited(false);
        $this->assertFalse($this->phone->getStockUnlimited());
        $this->assertFalse((bool) $this->phone->hasWarehouseWithUnlimitedStock());
    }

    public function testMinusOneIsNotUnlimitedWhenCheckboxEnabled(): void
    {
        $this->enableCheckbox();

        // A real quantity of -1 with the Unlimited flag off must NOT read as unlimited.
        $this->phone->setStockLevel('-1');
        $this->phone->setStockUnlimited(false);

        $this->assertSame('-1', $this->phone->getStockLevel());
        $this->assertFalse(
            (bool) $this->phone->hasWarehouseWithUnlimitedStock(),
            '-1 is a real quantity, not unlimited, in checkbox mode'
        );
    }

    public function testUnlimitedStockIsNotDecremented(): void
    {
        $this->enableCheckbox();

        $this->phone->setStockLevel('10');
        $this->phone->setStockUnlimited(true);

        $orderItem = OrderItem::create(['ProductID' => $this->phone->ID, 'Quantity' => 3]);
        $this->phone->decrementStock($orderItem);

        $this->assertSame('10', $this->phone->getStockLevel(), 'unlimited stock is not decremented');
    }

    public function testVariationGridGainsStockColumn(): void
    {
        // A warehouse exists (fixture), so the inline Stock column is contributed.
        $displayFields = [];
        $this->phone->extend('updateVariationEditableColumns', $displayFields);

        $this->assertArrayHasKey('StockLevel', $displayFields);
        $this->assertArrayNotHasKey('StockUnlimited', $displayFields, 'no Unlimited column while the feature is off');
    }

    public function testVariationGridGainsUnlimitedColumnWhenEnabled(): void
    {
        $this->enableCheckbox();

        $displayFields = [];
        $this->phone->extend('updateVariationEditableColumns', $displayFields);

        $this->assertArrayHasKey('StockLevel', $displayFields);
        $this->assertArrayHasKey('StockUnlimited', $displayFields);
    }

    public function testDetailStockGridHasHeaderRowAndTitledColumns(): void
    {
        $grid = $this->detailStockGrid();
        $config = $grid->getConfig();

        $this->assertNotNull(
            $config->getComponentByType(GridFieldTitleHeader::class),
            'the detail Stock grid shows a column-title header row'
        );

        $displayFields = $config->getComponentByType(GridFieldEditableColumns::class)->getDisplayFields($grid);
        $this->assertSame('Warehouse', $displayFields['Title']['title']);
        $this->assertSame('Quantity', $displayFields['Quantity']['title']);
    }

    public function testDetailStockGridGainsUnlimitedColumnWhenEnabled(): void
    {
        $grid = $this->detailStockGrid();
        $displayFields = $grid->getConfig()
            ->getComponentByType(GridFieldEditableColumns::class)->getDisplayFields($grid);
        $this->assertArrayNotHasKey('Unlimited', $displayFields, 'no Unlimited column while the feature is off');

        $this->enableCheckbox();
        $grid = $this->detailStockGrid();
        $displayFields = $grid->getConfig()
            ->getComponentByType(GridFieldEditableColumns::class)->getDisplayFields($grid);
        $this->assertArrayHasKey('Unlimited', $displayFields);
    }

    private function detailStockGrid(): GridField
    {
        // 'phone' has no variations, so ProductStockExtension builds the per-warehouse Stock grid.
        $grid = $this->phone->getCMSFields()->dataFieldByName('StockLevels');
        $this->assertInstanceOf(GridField::class, $grid);

        return $grid;
    }
}
