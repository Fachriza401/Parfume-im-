<?php

namespace App\Exceptions;

class InsufficientStockException extends BusinessRuleException
{
    public function __construct(string $productName, int $available, int $requested)
    {
        parent::__construct(
            message: "Stok {$productName} tidak mencukupi. Tersedia {$available}, diminta {$requested}.",
            statusCode: 422,
            errorCode: 'INSUFFICIENT_STOCK',
            errors: [
                'product' => [$productName],
                'available_stock' => [$available],
                'requested_quantity' => [$requested],
            ]
        );
    }
}
