<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $format = static fn (array $destinations): array => array_map(
            static fn (array $destination): array => $destination + [
                'configured' => filled($destination['account_number'])
                    && filled($destination['account_name']),
            ],
            $destinations
        );

        return ApiResponse::success([
            'banks' => $format(config('payments.banks', [])),
            'e_wallets' => $format(config('payments.e_wallets', [])),
        ]);
    }
}
