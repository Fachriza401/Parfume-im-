<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Password comes from config (populated by .env), never hardcoded, so
        // `config:cache` deployments keep working and no secret lives in git.
        $password = (string) config('parfum.admin_password');

        if (strlen($password) < 8) {
            $this->command?->warn('ADMIN_PASSWORD belum diset (minimal 8 karakter), memakai nilai acak.');

            $password = Str::random(32);
        }

        User::updateOrCreate(
            ['email' => config('parfum.admin_email', 'admin@parfumim.test')],
            [
                'name' => 'Parfum IM Admin',
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'phone' => '081200000001',
                'email_verified_at' => now(),
            ]
        );
    }
}
