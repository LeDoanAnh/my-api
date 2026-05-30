<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Dọn dẹp dữ liệu cũ (Xóa sạch để tạo mới cho chuẩn)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('asset_requests')->truncate();
        DB::table('submission_locations')->truncate();
        DB::table('submission_step_contents')->truncate();
        DB::table('approval_log')->whereIn('submission_id', [20, 21, 22, 23])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $scenarios = [
            // --- NHÓM 1: 2 ĐƠN ĐÃ BÀN GIAO (handler_id = 3, có borrow_date) ---
            [
                'id' => 20,
                'title' => 'Mượn thiết bị tổ chức Gala Chào Tân Sinh Viên',
                'status' => 'approved',
                'handler' => 3,
                'items' => [
                    ['id' => 1, 'n' => 'Loa Sony chính'], ['id' => 3, 'n' => 'Máy chiếu Epson'],
                    ['id' => 5, 'n' => 'Mic không dây'], ['id' => 10, 'n' => 'TV Samsung 65 inch']
                ]
            ],
            [
                'id' => 21,
                'title' => 'Cấp phát vật tư thi tập trung học kỳ 2',
                'status' => 'approved',
                'handler' => 3,
                'items' => [
                    ['id' => 4, 'n' => '10 Ram giấy A4'], ['id' => 6, 'n' => '2 Hộp bút viết bảng'],
                    ['id' => 9, 'n' => '3 Hộp mực in'], ['id' => 2, 'n' => '5 Bình nước Lavie']
                ]
            ],

            // --- NHÓM 2: 2 ĐƠN CHƯA BÀN GIAO (handler_id = null, borrow_date = null) ---
            [
                'id' => 22,
                'title' => 'Yêu cầu thiết bị cho Workshop AI & Mobile',
                'status' => 'approved',
                'handler' => null,
                'items' => [
                    ['id' => 7, 'n' => 'Laptop Dell demo'], ['id' => 8, 'n' => 'Sạc dự phòng 20k'],
                    ['id' => 3, 'n' => 'Máy chiếu Lab 402'], ['id' => 5, 'n' => 'Mic trợ giảng Sony']
                ]
            ],
            [
                'id' => 23,
                'title' => 'Mượn vật tư phục vụ họp Hội đồng trường',
                'status' => 'approved',
                'handler' => null,
                'items' => [
                    ['id' => 1, 'n' => 'Dàn âm thanh hội trường'], ['id' => 10, 'n' => 'Màn hình hiển thị'],
                    ['id' => 2, 'n' => '20 bình nước uống'], ['id' => 4, 'n' => '5 Ram giấy in hồ sơ']
                ]
            ],
        ];

        foreach ($scenarios as $scene) {
            // 1. Tạo/Cập nhật Tờ trình (Submissions)
            DB::table('submissions')->updateOrInsert(
                ['id' => $scene['id']],
                [
                    'title' => $scene['title'],
                    'content' => 'Nội dung chi tiết yêu cầu cho: ' . $scene['title'],
                    'creator_id' => 4, // Cán bộ Mai
                    'category_id' => 1,
                    'status' => $scene['status'],
                    'created_at' => now()->subDays(2),
                ]
            );

            // 2. Tạo Log phê duyệt (Để đảm bảo đơn này đã "Approved")
            DB::table('approval_log')->insert([
                'submission_id' => $scene['id'],
                'approver_id' => 3, // Trưởng phòng Đức duyệt
                'action' => 'approved',
                'comment' => 'Đã phê duyệt, bộ phận kho chuẩn bị bàn giao.',
                'created_at' => now()->subDays(1),
            ]);

            // 3. Tạo 4 món đồ cho mỗi đơn (Sử dụng 2 cột ngày)
            foreach ($scene['items'] as $item) {
                DB::table('asset_requests')->insert([
                    'submission_id' => $scene['id'],
                    'asset_id' => $item['id'],
                    'borrower_id' => 4, // Người mượn là Mai
                    'handler_id' => $scene['handler'], // Nhân viên giao (Đức hoặc NULL)

                    // Ngày hẹn: Luôn có (Mặc định hẹn 1 ngày sau khi tạo đơn)
                    'expected_borrow_date' => Carbon::now()->subDays(1)->setHour(8)->setMinute(0),

                    // Ngày thực tế: Có nếu đã bàn giao, NULL nếu chưa bàn giao
                    'borrow_date' => $scene['handler'] ? now()->subHours(2) : null,

                    'expected_return_date' => Carbon::now()->addDays(2),
                    'note' => $item['n'],
                    'created_at' => now(),
                ]);
            }

            // 4. Thêm lời nhắn bổ sung
            DB::table('submission_step_contents')->insert([
                'submission_id' => $scene['id'],
                'step_order' => 1,
                'target_dept_id' => 1,
                'content_text' => 'Ghi chú: Đề nghị bàn giao thiết bị đúng tình trạng hoạt động tốt.',
                'created_at' => now(),
            ]);
        }
    }
}
