<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('assets')->insert([
            ['id' => 1, 'department_id' => 2, 'asset_name' => 'May chieu Epson EB-X51', 'asset_code' => 'IT-PRJ-001', 'status' => 'ready', 'type' => 'returnable', 'unit' => 'bo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'department_id' => 2, 'asset_name' => 'Laptop Dell Latitude 5440', 'asset_code' => 'IT-LAP-001', 'status' => 'ready', 'type' => 'returnable', 'unit' => 'chiec', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'department_id' => 2, 'asset_name' => 'Bo micro khong day Shure', 'asset_code' => 'IT-MIC-001', 'status' => 'ready', 'type' => 'returnable', 'unit' => 'bo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'department_id' => 2, 'asset_name' => 'Loa keo Sony V73', 'asset_code' => 'IT-SPK-001', 'status' => 'ready', 'type' => 'returnable', 'unit' => 'bo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'department_id' => 1, 'asset_name' => 'Giay A4 Double A', 'asset_code' => 'TC-PAP-001', 'status' => 'in stock', 'type' => 'consumable', 'unit' => 'ram', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'department_id' => 1, 'asset_name' => 'But long bang xanh', 'asset_code' => 'TC-PEN-001', 'status' => 'in stock', 'type' => 'consumable', 'unit' => 'hop', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'department_id' => 1, 'asset_name' => 'Nuoc uong Lavie 19L', 'asset_code' => 'TC-WAT-001', 'status' => 'in stock', 'type' => 'consumable', 'unit' => 'binh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'department_id' => 4, 'asset_name' => 'Bo backdrop di dong', 'asset_code' => 'CTSV-BD-001', 'status' => 'ready', 'type' => 'returnable', 'unit' => 'bo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
