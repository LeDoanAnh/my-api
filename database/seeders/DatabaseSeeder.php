<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DoAnSeeder::class,     // Tạo User, Role, Department
            AssetSeeder::class,    // Tạo danh sách đồ trong kho
            LocationSeeder::class, // Tạo danh mục địa điểm
            AssetSubmissionSeeder::class,
            ]
        );
    }
}
