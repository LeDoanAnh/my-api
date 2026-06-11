<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DoAnSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'role_name' => 'Admin', 'description' => 'Quan tri he thong', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'role_name' => 'User', 'description' => 'Nguoi tao to trinh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'role_name' => 'Lanh dao', 'description' => 'Tai khoan duyet cua phong ban', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'role_name' => 'Can bo', 'description' => 'Can bo xu ly nghiep vu', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('departments')->insert([
            ['id' => 1, 'dept_name' => 'Phong Tai chinh', 'location_desc' => 'Tang 2 nha A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'dept_name' => 'Phong Cong nghe', 'location_desc' => 'Tang 4 nha B', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'dept_name' => 'Phong Dao tao', 'location_desc' => 'Tang 1 nha Hieu bo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'dept_name' => 'Phong Cong tac Sinh vien', 'location_desc' => 'Tang 1 nha C', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'dept_name' => 'Ban Giam hieu', 'location_desc' => 'Tang 5 nha Hieu bo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'dept_name' => 'Van phong Doan', 'location_desc' => 'Khu sinh hoat sinh vien', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $users = [
            ['id' => 1, 'username' => 'admin', 'full_name' => 'He thong Quan tri', 'email' => 'admin@demo.local', 'department_id' => 1, 'roles' => [1, 3]],
            ['id' => 2, 'username' => 'mai', 'full_name' => 'Tran Thi Mai', 'email' => 'mai@demo.local', 'department_id' => 4, 'roles' => [2]],
            ['id' => 3, 'username' => 'duc', 'full_name' => 'Pham Minh Duc - Tai chinh', 'email' => 'duc@demo.local', 'department_id' => 1, 'roles' => [3]],
            ['id' => 4, 'username' => 'linh', 'full_name' => 'Nguyen Thuy Linh - Cong nghe', 'email' => 'linh@demo.local', 'department_id' => 2, 'roles' => [3]],
            ['id' => 5, 'username' => 'hoa', 'full_name' => 'Le Minh Hoa - Dao tao', 'email' => 'hoa@demo.local', 'department_id' => 3, 'roles' => [3]],
            ['id' => 6, 'username' => 'an', 'full_name' => 'Vo Hoang An - CTSV', 'email' => 'an@demo.local', 'department_id' => 4, 'roles' => [3]],
            ['id' => 7, 'username' => 'khanh', 'full_name' => 'Do Quang Khanh - Ban Giam hieu', 'email' => 'khanh@demo.local', 'department_id' => 5, 'roles' => [3]],
            ['id' => 8, 'username' => 'nga', 'full_name' => 'Nguyen Thu Nga - Van phong Doan', 'email' => 'nga@demo.local', 'department_id' => 6, 'roles' => [3]],
            ['id' => 9, 'username' => 'tuan', 'full_name' => 'Bui Anh Tuan - Can bo kho', 'email' => 'tuan@demo.local', 'department_id' => 2, 'roles' => [4]],
        ];

        foreach ($users as $user) {
            DB::table('users')->insert([
                'id' => $user['id'],
                'username' => $user['username'],
                'password' => Hash::make('123456'),
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'department_id' => $user['department_id'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($user['roles'] as $roleId) {
                DB::table('role_user')->insert([
                    'user_id' => $user['id'],
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('submission_categories')->insert([
            ['id' => 1, 'category_name' => 'Dang ky su kien', 'description' => 'Quy trinh duyet su kien, phong, thiet bi', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'category_name' => 'Muon vat tu thiet bi', 'description' => 'Quy trinh muon va ban giao tai san', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'category_name' => 'De xuat tai chinh', 'description' => 'Quy trinh xin kinh phi va mua sam', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $configs = [
            [1, 1, 3, 6], [1, 2, 3, 4], [1, 3, 3, 1], [1, 4, 3, 5],
            [2, 1, 3, 2], [2, 2, 3, 1], [2, 3, 3, 5],
            [3, 1, 3, 1], [3, 2, 3, 5],
        ];

        foreach ($configs as $config) {
            DB::table('approval_configs')->insert([
                'category_id' => $config[0],
                'step_order' => $config[1],
                'target_role_id' => $config[2],
                'target_dept_id' => $config[3],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
