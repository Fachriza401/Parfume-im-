<?php

return [
    'banks' => [
        ['code' => 'bca', 'name' => 'BCA', 'account_number' => env('PAYMENT_BCA_ACCOUNT_NUMBER', ''), 'account_name' => env('PAYMENT_BCA_ACCOUNT_NAME', '')],
        ['code' => 'mandiri', 'name' => 'Mandiri', 'account_number' => env('PAYMENT_MANDIRI_ACCOUNT_NUMBER', ''), 'account_name' => env('PAYMENT_MANDIRI_ACCOUNT_NAME', '')],
        ['code' => 'bni', 'name' => 'BNI', 'account_number' => env('PAYMENT_BNI_ACCOUNT_NUMBER', ''), 'account_name' => env('PAYMENT_BNI_ACCOUNT_NAME', '')],
        ['code' => 'bri', 'name' => 'BRI', 'account_number' => env('PAYMENT_BRI_ACCOUNT_NUMBER', ''), 'account_name' => env('PAYMENT_BRI_ACCOUNT_NAME', '')],
    ],
    'e_wallets' => [
        ['code' => 'gopay', 'name' => 'GoPay', 'account_number' => env('PAYMENT_GOPAY_NUMBER', ''), 'account_name' => env('PAYMENT_GOPAY_ACCOUNT_NAME', '')],
        ['code' => 'ovo', 'name' => 'OVO', 'account_number' => env('PAYMENT_OVO_NUMBER', ''), 'account_name' => env('PAYMENT_OVO_ACCOUNT_NAME', '')],
        ['code' => 'dana', 'name' => 'DANA', 'account_number' => env('PAYMENT_DANA_NUMBER', ''), 'account_name' => env('PAYMENT_DANA_ACCOUNT_NAME', '')],
        ['code' => 'shopeepay', 'name' => 'ShopeePay', 'account_number' => env('PAYMENT_SHOPEEPAY_NUMBER', ''), 'account_name' => env('PAYMENT_SHOPEEPAY_ACCOUNT_NAME', '')],
    ],
];
