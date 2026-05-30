<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run()
    {
        $locations = [
            ['department_id' => 1, 'location_name' => 'Grand Hall A', 'capacity' => '500', 'status' => 'available'],
            ['department_id' => 2, 'location_name' => 'Meeting Room 2', 'capacity' => '20', 'status' => 'maintenance'],
            ['department_id' => 1, 'location_name' => 'VIP Lounge', 'capacity' => '10', 'status' => 'available'],
            ['department_id' => 1, 'location_name' => 'Central Stadium', 'capacity' => '100', 'status' => 'available'],
            ['department_id' => 2, 'location_name' => 'IT Lab 402', 'capacity' => '40', 'status' => 'in use'],
            ['department_id' => 1, 'location_name' => 'Basement Storage', 'capacity' => '5', 'status' => 'available'],
            ['department_id' => 2, 'location_name' => 'Grand Hall B', 'capacity' => '300', 'status' => 'maintenance'],
            ['department_id' => 2, 'location_name' => 'Audio Studio', 'capacity' => '15', 'status' => 'available'],
            ['department_id' => 1, 'location_name' => 'Reception Room', 'capacity' => '30', 'status' => 'available'],
            ['department_id' => 2, 'location_name' => 'Multi-purpose Gym', 'capacity' => '1000', 'status' => 'available'],
        ];

        foreach ($locations as $loc) {
            \App\Models\Location::create($loc);
        }
    }
}
