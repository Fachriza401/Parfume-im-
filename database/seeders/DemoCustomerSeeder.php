<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'customer@parfumim.test'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('Customer#123'),
                'role' => User::ROLE_CUSTOMER,
                'phone' => '081200000002',
                'email_verified_at' => now(),
            ]
        );
    }
}
