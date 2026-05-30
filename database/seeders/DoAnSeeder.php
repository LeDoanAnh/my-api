<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DoAnSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Xóa dữ liệu cũ theo đúng thứ tự
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('role_user')->truncate();
        DB::table('approval_log')->truncate();
        DB::table('submissions')->truncate();
        DB::table('users')->truncate();
        DB::table('departments')->truncate();
        DB::table('submission_categories')->truncate();
        DB::table('roles')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Tạo Roles
        DB::table('roles')->insert([
            ['id' => 1, 'role_name' => 'Admin', 'description' => 'Quản trị hệ thống'],
            ['id' => 2, 'role_name' => 'User', 'description' => 'Sinh viên/Thành viên'],
            ['id' => 3, 'role_name' => 'Lãnh đạo', 'description' => 'Trưởng/Phó phòng'],
            ['id' => 4, 'role_name' => 'Cán bộ', 'description' => 'Cán bộ nghiệp vụ - Cấp dưới trực tiếp'],
        ]);

        // 2. Tạo Departments
        DB::table('departments')->insert([
            ['id' => 1, 'dept_name' => 'Phòng Tài chính'],
            ['id' => 2, 'dept_name' => 'Phòng Công nghệ'],
        ]);

        // 3. Tạo Users (PHẢI TẠO ADMIN ID=1 TRƯỚC)
        $userData = [
            [
                'id' => 1,
                'username' => 'admin',
                'full_name' => 'Hệ thống Quản trị',
                'role_id' => 1,
                'dept_id' => 1
            ],
            [
                'id' => 3,
                'username' => 'truongphong',
                'full_name' => 'Phạm Minh Đức (Trưởng phòng)',
                'role_id' => 3,
                'dept_id' => 1
            ],
            [
                'id' => 4,
                'username' => 'canbo',
                'full_name' => 'Trần Thị Mai (Cán bộ)',
                'role_id' => 4,
                'dept_id' => 1
            ],
        ];

        foreach ($userData as $u) {
            DB::table('users')->insert([
                'id' => $u['id'],
                'username' => $u['username'],
                'password' => Hash::make('123456'),
                'full_name' => $u['full_name'],
                'email' => $u['username'] . "@sv.edu.vn",
                'department_id' => $u['dept_id'],
                'status' => 'active',
                'created_at' => now(),
            ]);
            // Gán quyền vào bảng trung gian
            DB::table('role_user')->insert(['user_id' => $u['id'], 'role_id' => $u['role_id']]);
        }

        // 4. Tạo Categories
        DB::table('submission_categories')->insert([
            ['id' => 1, 'category_name' => 'Tờ trình tài chính', 'description' => 'Chi phí, mua sắm'],
            ['id' => 2, 'category_name' => 'Tờ trình nhân sự', 'description' => 'Nghỉ phép, khen thưởng'],
        ]);

        // --- 5. TẠO DỮ LIỆU TỜ TRÌNH ---

        // NHÓM A: ĐƠN DO TRƯỞNG PHÒNG TẠO (Check Tab: Của tôi)
        $myOwnSubmissions = [
            ['id' => 1, 'title' => 'TP - Xin phê duyệt ngân sách quý 2', 'status' => 'pending'],
            ['id' => 2, 'title' => 'TP - Xin nghỉ phép đi công tác', 'status' => 'approved'],
            ['id' => 3, 'title' => 'TP - Đề xuất sửa chữa văn phòng', 'status' => 'rejected'],
        ];

        foreach ($myOwnSubmissions as $sub) {
            DB::table('submissions')->insert([
                'id' => $sub['id'],
                'title' => $sub['title'],
                'content' => 'Nội dung chi tiết của: ' . $sub['title'], // Đã thêm để tránh lỗi 1364
                'creator_id' => 3,
                'category_id' => 1,
                'status' => $sub['status'],
                'created_at' => now()->subDays(2),
            ]);

            if ($sub['status'] !== 'pending') {
                DB::table('approval_log')->insert([
                    'submission_id' => $sub['id'],
                    'approver_id' => 1, // Đã có Admin ID=1 nên không lỗi khóa ngoại nữa
                    'action' => $sub['status'],
                    'comment' => 'Cấp trên đã phản hồi tờ trình của Trưởng phòng',
                    'created_at' => now(),
                ]);
            }
        }

        // NHÓM B: ĐƠN DO CÁN BỘ (ROLE 4) TẠO GỬI TRƯỞNG PHÒNG (Check Tab: Cần duyệt)
        $staffSubmissions = [
            ['id' => 4, 'title' => 'Cán bộ Mai - Xin thanh toán hóa đơn điện', 'status' => 'pending'],
            ['id' => 5, 'title' => 'Cán bộ Mai - Đề xuất mua máy in', 'status' => 'approved'],
            ['id' => 6, 'title' => 'Cán bộ Mai - Xin hỗ trợ trực ngoài giờ', 'status' => 'rejected'],
            ['id' => 7, 'title' => 'Cán bộ Mai - Báo cáo thu chi tháng', 'status' => 'pending'],
        ];

        foreach ($staffSubmissions as $sub) {
            DB::table('submissions')->insert([
                'id' => $sub['id'],
                'title' => $sub['title'],
                'content' => 'Nội dung tờ trình từ nhân viên gửi Trưởng phòng cho: ' . $sub['title'],
                'creator_id' => 4,
                'category_id' => 1,
                'status' => $sub['status'],
                'created_at' => now(),
            ]);

            // Trưởng phòng (User 3) thực hiện ký các đơn đã xong
            if ($sub['status'] !== 'pending') {
                DB::table('approval_log')->insert([
                    'submission_id' => $sub['id'],
                    'approver_id' => 3,
                    'action' => $sub['status'],
                    'comment' => 'Đã kiểm tra và phê duyệt nội dung của cán bộ nghiệp vụ',
                    'created_at' => now(),
                ]);
            }
        }
    }
}
