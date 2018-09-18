<?php

namespace SilverShop\Stock\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverShop\Stock\Exceptions\BuyableNotEnoughStockException;

/**
 * Checks to confirm that the user can purchase the given quantity of the
 * buyable.
 */
class OrderExtension extends DataExtension
{
    public function beforeAdd($buyable, $quantity, $filter)
    {
        if(!$buyable->canPurchase(null, $quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }

    public function afterAdd($item, $buyable, $quantity, $filter)
    {
        if(!$buyable->canPurchase(null, $item->Quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }

    public function beforeSetQuantity($buyable, $quantity, $filter)
    {
        if(!$buyable->canPurchase(null, $quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }
}
