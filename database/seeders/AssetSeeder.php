<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Asset;

class AssetSeeder extends Seeder
{
   public function run()
    {
        $assets = [
            ['department_id' => 1, 'asset_name' => 'Sony Speaker v73', 'asset_code' => 'AS-001', 'status' => 'ready', 'unit' => 'Unit'],
            ['department_id' => 2, 'asset_name' => 'Lavie Water 19L', 'asset_code' => 'CS-001', 'status' => 'in stock', 'unit' => 'Tank'],
            ['department_id' => 2, 'asset_name' => 'Epson Projector X41', 'asset_code' => 'AS-002', 'status' => 'borrowed', 'unit' => 'Unit'],
            ['department_id' => 1, 'asset_name' => 'Double A A4 Paper', 'asset_code' => 'CS-002', 'status' => 'out of stock', 'unit' => 'Ream'],
            ['department_id' => 2, 'asset_name' => 'Wireless Mic Sony', 'asset_code' => 'AS-003', 'status' => 'ready', 'unit' => 'Set'],
            ['department_id' => 2, 'asset_name' => 'Whiteboard Markers', 'asset_code' => 'CS-003', 'status' => 'in stock', 'unit' => 'Box'],
            ['department_id' => 1, 'asset_name' => 'Dell Latitude Laptop', 'asset_code' => 'AS-004', 'status' => 'repairing', 'unit' => 'Unit'],
            ['department_id' => 1, 'asset_name' => 'Powerbank 20k mAh', 'asset_code' => 'AS-005', 'status' => 'ready', 'unit' => 'Unit'],
            ['department_id' => 2, 'asset_name' => 'HP Ink 107a', 'asset_code' => 'CS-004', 'status' => 'in stock', 'unit' => 'Box'],
            ['department_id' => 1, 'asset_name' => 'Samsung TV 65 inch', 'asset_code' => 'AS-006', 'status' => 'ready', 'unit' => 'Unit'],
        ];

        foreach ($assets as $asset) {
            \App\Models\Asset::create($asset);
        }
    }
}
