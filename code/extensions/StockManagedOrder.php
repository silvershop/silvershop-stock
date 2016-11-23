<?php

/**
 * Decrements the stock value on products after they have been purchased
 *
 * @package silvershop-stock
 */
class StockManagedOrder extends DataExtension
{
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
