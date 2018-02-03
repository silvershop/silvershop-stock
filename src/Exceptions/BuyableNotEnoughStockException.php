<?php

namespace SilverShop\Stock\Exceptions;

use Exception;

class BuyableNotEnoughStockException extends Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        if(!$message) {
            $message = _t('BuyableNotEnoughStockException.OUT_OF_STOCK', 'This product does not have enough stock to fulfil your order');
        }

        return parent::__construct($message, $code, $previous);
    }
}
