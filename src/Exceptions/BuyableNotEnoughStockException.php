<?php

declare(strict_types=1);

namespace SilverShop\Stock\Exceptions;

use Exception;
use Throwable;

class BuyableNotEnoughStockException extends Exception
{
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        if (!$message) {
            $message = _t('BuyableNotEnoughStockException.OUT_OF_STOCK', 'This product does not have enough stock to fulfil your order');
        }

        parent::__construct($message, $code, $previous);
    }
}
