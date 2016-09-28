<?php

/**
 * Decrements the stock value on products after they have been purchased
 *
 * @package silvershop-stock
 */
class StockManagedOrder extends DataExtension
{
    public function beforeAdd($buyable, $quantity, $filter)
    {
        if(!$buyable->canPurchase(null, $quantity)) {
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
