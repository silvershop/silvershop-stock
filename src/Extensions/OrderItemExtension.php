<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverStripe\Core\Extension;

/**
 * Decrements the available stock when the order is placed.
 */
class OrderItemExtension extends Extension
{
    public function onPlacement(): void
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
