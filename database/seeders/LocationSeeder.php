<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('locations')->insert([
            ['id' => 1, 'location_code' => 'HALL-A', 'department_id' => 4, 'location_name' => 'Hoi truong A', 'address' => 'Tang 1 nha A', 'capacity' => '500', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'location_code' => 'ROOM-B201', 'department_id' => 3, 'location_name' => 'Phong hoc B201', 'address' => 'Tang 2 nha B', 'capacity' => '80', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'location_code' => 'LAB-402', 'department_id' => 2, 'location_name' => 'Phong lab 402', 'address' => 'Tang 4 nha B', 'capacity' => '45', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'location_code' => 'MEET-501', 'department_id' => 5, 'location_name' => 'Phong hop Ban Giam hieu', 'address' => 'Tang 5 nha Hieu bo', 'capacity' => '30', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'location_code' => 'YOUTH-YARD', 'department_id' => 6, 'location_name' => 'San sinh hoat Doan', 'address' => 'Khu sinh vien', 'capacity' => '300', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
