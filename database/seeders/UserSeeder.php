<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ledoananhhtg2003@gmail.com'], 
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'), // Mật khẩu để test
            ]
        );
    }
}