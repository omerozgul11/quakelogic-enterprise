<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(string $sku, float $available, float $requested): self
    {
        return new self(sprintf(
            'Insufficient stock for %s: %s on hand, %s requested.',
            $sku,
            rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($requested, 3, '.', ''), '0'), '.'),
        ));
    }
}
