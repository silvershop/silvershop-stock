<?php

/**
 * @package shop_stock
 */
class StockManagedOrderItem extends DataExtension
{
    
    public function onPlacement()
    {
        if ($this->owner->ProductVariationID) {
            if ($variation = $this->owner->ProductVariation()) {
                $variation->decrementStock($this->owner);
            }
        } elseif ($this->owner->ProductID) {
            if ($product = $this->owner->Product()) {
                $product->decrementStock($this->owner);
            }
        }
    }
}
