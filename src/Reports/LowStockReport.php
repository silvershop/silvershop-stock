<?php

declare(strict_types=1);

namespace SilverShop\Stock\Reports;

use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Reports\Report;

/**
 * Products / variations at or below the low-stock threshold, per warehouse — so managers can reorder before
 * anything sells out. Unlimited-stock records are excluded. Lives in the stock module because it reports its
 * own data. Threshold is configurable:
 *
 *   SilverShop\Stock\Reports\LowStockReport:
 *     low_stock_threshold: 5
 */
class LowStockReport extends Report
{
    private static int $low_stock_threshold = 5;

    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Low stock');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Products and variations at or below the low-stock threshold (unlimited stock excluded).');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 400;
    }

    public function sourceRecords($params = null)
    {
        $threshold = (int) self::config()->get('low_stock_threshold');

        return ProductWarehouseStock::get()
            ->exclude('Quantity', '-1')                          // -1 = unlimited sentinel
            ->filter('Unlimited', 0)                             // exclude explicit-unlimited (checkbox mode)
            ->where(['CAST("Quantity" AS SIGNED) <= ?' => $threshold])
            ->sort('Quantity', 'ASC');
    }

    public function columns()
    {
        return [
            'BuyableTitle' => _t(__CLASS__ . '.ColProduct', 'Product'),
            'VariationTitle' => _t(__CLASS__ . '.ColVariation', 'Variation'),
            'Title' => _t(__CLASS__ . '.ColWarehouse', 'Warehouse'),
            'Quantity' => _t(__CLASS__ . '.ColStock', 'Stock'),
        ];
    }
}
