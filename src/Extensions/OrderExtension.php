<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Stock\Exceptions\BuyableNotEnoughStockException;

/**
 * Checks to confirm that the user can purchase the given quantity of the
 * buyable.
 */
class OrderExtension extends Extension
{
    public function beforeAdd($buyable, int $quantity, array $filter): void
    {
        if (method_exists($buyable, 'canPurchase') && !$buyable->canPurchase(null, $quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }

    public function afterAdd($item, $buyable, int $quantity, array $filter): void
    {
        if (method_exists($buyable, 'canPurchase') && !$buyable->canPurchase(null, (int) $item->Quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }

    public function beforeSetQuantity($buyable, int $quantity, array $filter): void
    {
        if (method_exists($buyable, 'canPurchase') && !$buyable->canPurchase(null, $quantity)) {
            throw new BuyableNotEnoughStockException();
        }
    }
}
