<?php

namespace SilverShop\Stock\Extensions;

use SilverStripe\ORM\DataExtension;

/**
 * Decrements the available stock when the order is placed.
 *
 */
class OrderItemExtension extends DataExtension
{
    public function onPlacement()
    {
        if ($this->owner->ProductVariationID) {
            if ($variation = $this->owner->ProductVariation()) {
                if ($variation->hasMethod('decrementStock')) {
                    $variation->decrementStock($this->owner);
                }
            }
        } elseif ($this->owner->ProductID) {
            if ($product = $this->owner->Product()) {
                if ($product->hasMethod('decrementStock')) {
                    $product->decrementStock($this->owner);
                }
            }
        }
    }
}
