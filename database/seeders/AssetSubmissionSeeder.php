<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $makeSubmission = function (array $data): void {
            DB::table('submissions')->insert([
                'id' => $data['id'],
                'title' => $data['title'],
                'content' => $data['content'],
                'creator_id' => $data['creator_id'],
                'category_id' => $data['category_id'],
                'status' => $data['status'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'created_at' => $data['created_at'] ?? now(),
                'updated_at' => $data['updated_at'] ?? now(),
            ]);
        };

        $makeStep = function (int $submissionId, int $order, int $deptId, string $note): int {
            return DB::table('submission_step_contents')->insertGetId([
                'submission_id' => $submissionId,
                'step_order' => $order,
                'target_dept_id' => $deptId,
                'content_text' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $makeLog = function (int $submissionId, int $stepId, int $approverId, string $action, string $comment, int $hoursAgo = 24): void {
            DB::table('approval_log')->insert([
                'submission_id' => $submissionId,
                'step_content_id' => $stepId,
                'approver_id' => $approverId,
                'action' => $action,
                'comment' => $comment,
                'created_at' => now()->subHours($hoursAgo),
                'updated_at' => now()->subHours($hoursAgo),
            ]);
        };

        $makeNotification = function (int $userId, int $submissionId, string $type, string $title, string $message): void {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'submission_id' => $submissionId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        // 1. Moi tao, dang cho Van phong Doan duyet step dau.
        $makeSubmission([
            'id' => 1,
            'title' => 'Dang ky ngay hoi CLB cong nghe',
            'content' => 'To chuc ngay hoi gioi thieu cac CLB cong nghe, can hoi truong, am thanh va vat tu truyen thong.',
            'creator_id' => 2,
            'category_id' => 1,
            'status' => 'pending',
            'start_time' => Carbon::now()->addDays(10)->setHour(8)->setMinute(0),
            'end_time' => Carbon::now()->addDays(10)->setHour(17)->setMinute(0),
            'created_at' => now()->subHours(2),
        ]);
        $makeStep(1, 1, 6, 'Van phong Doan xac nhan ke hoach to chuc.');
        $makeStep(1, 2, 4, 'Phong CTSV kiem tra quy mo va an toan sinh vien.');
        $makeStep(1, 3, 1, 'Phong Tai chinh tham dinh kinh phi vat tu.');
        $makeStep(1, 4, 5, 'Ban Giam hieu phe duyet cuoi.');
        DB::table('submission_locations')->insert([
            'submission_id' => 1,
            'location_id' => 1,
            'start_time' => Carbon::now()->addDays(10)->setHour(8)->setMinute(0),
            'end_time' => Carbon::now()->addDays(10)->setHour(17)->setMinute(0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $makeNotification(8, 1, 'pending_approval', 'Yeu cau phe duyet moi', 'To trinh dang cho Van phong Doan xu ly.');

        // 2. Da qua 2 phong, dang cho Tai chinh.
        $makeSubmission([
            'id' => 2,
            'title' => 'Workshop ky nang phong van cho sinh vien nam cuoi',
            'content' => 'Can hoi truong, may chieu va kinh phi in an tai lieu cho 150 sinh vien.',
            'creator_id' => 2,
            'category_id' => 1,
            'status' => 'pending',
            'start_time' => Carbon::now()->addDays(15)->setHour(13)->setMinute(0),
            'end_time' => Carbon::now()->addDays(15)->setHour(17)->setMinute(0),
            'created_at' => now()->subDays(1),
        ]);
        $s21 = $makeStep(2, 1, 6, 'Xac nhan chuong trinh phu hop hoat dong sinh vien.');
        $s22 = $makeStep(2, 2, 4, 'Kiem tra danh sach tham du va phuong an dieu phoi.');
        $makeStep(2, 3, 1, 'Tham dinh chi phi tai lieu va nuoc uong.');
        $makeStep(2, 4, 5, 'Phe duyet cap truong.');
        $makeLog(2, $s21, 8, 'approved', 'Dong y ve noi dung chuong trinh.', 22);
        $makeLog(2, $s22, 6, 'approved', 'Da kiem tra danh sach va phuong an to chuc.', 12);
        DB::table('asset_requests')->insert([
            ['submission_id' => 2, 'asset_id' => 1, 'borrower_id' => 2, 'handler_id' => null, 'expected_borrow_date' => Carbon::now()->addDays(15), 'expected_return_date' => Carbon::now()->addDays(16), 'note' => 'May chieu cho workshop', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['submission_id' => 2, 'asset_id' => 5, 'borrower_id' => 2, 'handler_id' => null, 'expected_borrow_date' => Carbon::now()->addDays(15), 'expected_return_date' => Carbon::now()->addDays(15), 'note' => '20 ram tai lieu', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $makeNotification(3, 2, 'pending_approval', 'Yeu cau phe duyet moi', 'To trinh dang cho Phong Tai chinh xu ly.');

        // 3. Muon thiet bi da duyet het va da ban giao mot phan.
        $makeSubmission([
            'id' => 3,
            'title' => 'Muon thiet bi livestream le tot nghiep',
            'content' => 'Muon laptop, micro, loa va phong hop de livestream le tot nghiep.',
            'creator_id' => 2,
            'category_id' => 2,
            'status' => 'approved',
            'start_time' => Carbon::now()->addDays(4)->setHour(7)->setMinute(30),
            'end_time' => Carbon::now()->addDays(4)->setHour(12)->setMinute(0),
            'created_at' => now()->subDays(4),
        ]);
        $s31 = $makeStep(3, 1, 2, 'Phong Cong nghe kiem tra thiet bi.');
        $s32 = $makeStep(3, 2, 1, 'Phong Tai chinh xac nhan vat tu tieu hao.');
        $s33 = $makeStep(3, 3, 5, 'Ban Giam hieu duyet su dung nguon luc.');
        $makeLog(3, $s31, 4, 'approved', 'Thiet bi san sang ban giao.', 70);
        $makeLog(3, $s32, 3, 'approved', 'Dong y cap vat tu kem theo.', 62);
        $makeLog(3, $s33, 7, 'approved', 'Phe duyet toan bo de xuat.', 50);
        DB::table('asset_requests')->insert([
            ['submission_id' => 3, 'asset_id' => 2, 'borrower_id' => 2, 'handler_id' => 9, 'expected_borrow_date' => Carbon::now()->addDays(4), 'borrow_date' => now()->subHours(3), 'expected_return_date' => Carbon::now()->addDays(5), 'note' => 'Laptop livestream', 'status' => 'borrowed', 'created_at' => now(), 'updated_at' => now()],
            ['submission_id' => 3, 'asset_id' => 3, 'borrower_id' => 2, 'handler_id' => 9, 'expected_borrow_date' => Carbon::now()->addDays(4), 'borrow_date' => now()->subHours(3), 'expected_return_date' => Carbon::now()->addDays(5), 'note' => 'Micro khong day', 'status' => 'borrowed', 'created_at' => now(), 'updated_at' => now()],
            ['submission_id' => 3, 'asset_id' => 4, 'borrower_id' => 2, 'handler_id' => null, 'expected_borrow_date' => Carbon::now()->addDays(4), 'borrow_date' => null, 'expected_return_date' => Carbon::now()->addDays(5), 'note' => 'Loa san khau', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('submission_locations')->insert([
            'submission_id' => 3,
            'location_id' => 4,
            'start_time' => Carbon::now()->addDays(4)->setHour(7)->setMinute(30),
            'end_time' => Carbon::now()->addDays(4)->setHour(12)->setMinute(0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $makeNotification(2, 3, 'approved', 'To trinh da duoc duyet', 'De xuat livestream le tot nghiep da duoc phe duyet.');

        // 4. Bi tu choi tai Ban Giam hieu.
        $makeSubmission([
            'id' => 4,
            'title' => 'De xuat mua bo man hinh LED san khau',
            'content' => 'De xuat mua bo man hinh LED phuc vu cac su kien lon trong nam.',
            'creator_id' => 2,
            'category_id' => 3,
            'status' => 'rejected',
            'start_time' => Carbon::now()->addDays(30),
            'end_time' => Carbon::now()->addDays(45),
            'created_at' => now()->subDays(5),
        ]);
        $s41 = $makeStep(4, 1, 1, 'Phong Tai chinh tham dinh ngan sach.');
        $s42 = $makeStep(4, 2, 5, 'Ban Giam hieu xem xet chu truong.');
        $makeLog(4, $s41, 3, 'approved', 'Kinh phi co the can doi neu duoc phe duyet chu truong.', 110);
        $makeLog(4, $s42, 7, 'rejected', 'Chua phu hop ke hoach mua sam nam nay.', 100);
        $makeNotification(2, 4, 'rejected', 'To trinh bi tu choi', 'De xuat mua man hinh LED chua duoc phe duyet.');

        // 5. Muon vat tu dang cho Tai chinh, da qua Cong nghe.
        $makeSubmission([
            'id' => 5,
            'title' => 'Muon thiet bi cho lop tap huan AI can ban',
            'content' => 'Can laptop demo, may chieu va vat tu in an cho lop tap huan.',
            'creator_id' => 2,
            'category_id' => 2,
            'status' => 'pending',
            'start_time' => Carbon::now()->addDays(7)->setHour(8)->setMinute(0),
            'end_time' => Carbon::now()->addDays(7)->setHour(11)->setMinute(30),
            'created_at' => now()->subHours(18),
        ]);
        $s51 = $makeStep(5, 1, 2, 'Phong Cong nghe xac nhan tinh san sang cua thiet bi.');
        $makeStep(5, 2, 1, 'Phong Tai chinh xac nhan vat tu tieu hao.');
        $makeStep(5, 3, 5, 'Ban Giam hieu phe duyet cuoi.');
        $makeLog(5, $s51, 4, 'approved', 'Da giu thiet bi cho lop tap huan.', 8);
        DB::table('asset_requests')->insert([
            ['submission_id' => 5, 'asset_id' => 1, 'borrower_id' => 2, 'handler_id' => null, 'expected_borrow_date' => Carbon::now()->addDays(7), 'expected_return_date' => Carbon::now()->addDays(8), 'note' => 'May chieu lop tap huan', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['submission_id' => 5, 'asset_id' => 6, 'borrower_id' => 2, 'handler_id' => null, 'expected_borrow_date' => Carbon::now()->addDays(7), 'expected_return_date' => Carbon::now()->addDays(7), 'note' => 'But viet bang', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $makeNotification(3, 5, 'pending_approval', 'Yeu cau phe duyet moi', 'To trinh muon thiet bi AI dang cho Tai chinh.');
    }
}
