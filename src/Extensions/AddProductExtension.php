<?php

declare(strict_types=1);

namespace SilverShop\Stock\Extensions;

use SilverShop\Model\Buyable;
use SilverStripe\Core\Extension;

class AddProductExtension extends Extension
{
    /**
     * If there is no stock across all products, then disable the add product
     * form.
     *
     * @param Buyable|null $product
     */
    public function updateAddProductForm(?Buyable $product = null): void
    {
        $data = $this->owner->getController()->data();

        if (method_exists($data, 'canPurchase') && !$data->canPurchase()) {
            $this->owner->makeReadonly();
        }
    }
}
