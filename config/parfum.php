<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parfum IM bootstrap account
    |--------------------------------------------------------------------------
    |
    | Used only by AdminSeeder. Set ADMIN_PASSWORD in your .env file; never
    | commit a real value to .env.example.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'admin@parfumim.test'),

    'admin_password' => env('ADMIN_PASSWORD', ''),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

];
