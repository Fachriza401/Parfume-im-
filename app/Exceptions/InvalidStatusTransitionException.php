<?php

namespace App\Exceptions;

class InvalidStatusTransitionException extends BusinessRuleException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            message: "Status pesanan tidak dapat diubah dari '{$from}' menjadi '{$to}'.",
            statusCode: 422,
            errorCode: 'INVALID_STATUS_TRANSITION',
            errors: [
                'status_order' => ["Transisi dari '{$from}' ke '{$to}' tidak diperbolehkan."],
            ]
        );
    }
}
