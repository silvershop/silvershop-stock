<?php

/**
 * @package silvershop-stock
 */
class StockManagedOrderItem extends DataExtension
{
    public function onPlacement()
    {
        $preventDecrement = false;

        $this->owner->extend('onBeforePlacementStockDecrement', $preventDecrement);

        if (!$preventDecrement) {
            if ($this->owner->ProductVariationID) {
                if ($variation = $this->owner->ProductVariation()) {
                    if ($variation->hasMethod('decrementStock')) {
                        $variation->decrementStock($this->owner);
                    }
                }
            }elseif ($this->owner->ProductID) {
                if ($product = $this->owner->Product()) {
                    if ($product->hasMethod('decrementStock')) {
                        $product->decrementStock($this->owner);
                    }
                }
            }

        }

    }
}
