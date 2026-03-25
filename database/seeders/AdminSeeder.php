<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Avoid duplicates on re-run
        User::where('email', 'admin@aerotrek.com')->delete();

        User::create([
            'name'           => 'Super Admin',
            'email'          => 'admin@aerotrek.com',
            'phone'          => '9999999999',
            'password'       => Hash::make('Admin@1234'),
            'company_name'   => 'AeroTrek',
            'wallet_balance' => 0.0,
            'kyc_status'     => 'verified',
            'is_admin'       => true,
            'email_verified' => true,
            'phone_verified' => true,
        ]);

        $this->command->info('✅ Admin user created → admin@aerotrek.com / Admin@1234');
    }
}