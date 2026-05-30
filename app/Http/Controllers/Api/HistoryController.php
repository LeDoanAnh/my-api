<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HistoryController extends Controller
{
    // ──────────────────────────────────────────────
    // Tab 1: Lịch sử mượn trả
    // Tất cả role - borrower_id = user đang login
    // ──────────────────────────────────────────────
    public function borrowHistory(Request $request)
    {
        $userId = $request->query('user_id');
        $search = $request->query('search');

        $query = AssetRequest::with([
                'asset',
                'submission',
                'handler',
                'borrower',
            ])
            ->where('borrower_id', $userId)
            ->where('status', 'returned')   // hoặc 'completed' tuỳ status thực tế
            ->whereNotNull('actual_return_date');

        if ($search) {
            $query->whereHas('submission', fn($q) =>
                $q->where('title', 'LIKE', "%{$search}%")
            )->orWhereHas('asset', fn($q) =>
                $q->where('asset_name', 'LIKE', "%{$search}%")
            );
        }

        // Group theo submission_id để gom nhiều asset trong 1 phiếu
        $grouped = $query
            ->orderBy('actual_return_date', 'desc')
            ->get()
            ->groupBy('submission_id');

        $data = $grouped->map(function ($items) {
            $first      = $items->first();
            $submission = $first->submission;
            $handler    = $first->handler;
            $borrower   = $first->borrower;

            return [
                'submission_id'   => $submission?->id,
                'submission_code' => 'TT-' . str_pad($submission?->id, 4, '0', STR_PAD_LEFT),
                'title'           => $submission?->title ?? 'Không có tiêu đề',
                'borrower_name'   => $borrower?->name ?? '-',
                'receiver_name'   => $handler?->name ?? '-',  // người thu hồi
                'completed_date'  => $first->actual_return_date
                    ? Carbon::parse($first->actual_return_date)->format('H:i - d/m/Y')
                    : null,
                'items' => $items->map(fn($ar) => [
                    'name'          => $ar->asset?->asset_name ?? '-',
                    'qty'           => 1,
                    'is_consumable' => $ar->asset?->type === 'consumable',
                ])->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    // Tab 2: Lịch sử bàn giao
    // Chỉ role 3 (trưởng phòng) & 4 (nhân viên phòng)
    // = tài sản của phòng mình đã được mượn & trả lại
    // ──────────────────────────────────────────────
    public function handoverHistory(Request $request)
    {
        $userId = $request->query('user_id');
        $search = $request->query('search');

        // Lấy department_id của user hiện tại
        $user = User::findOrFail($userId);
        $deptId = $user->department_id;
        $query = AssetRequest::with([
                'asset.department',
                'submission',
                'borrower',
                'handler',
            ])
            ->whereHas('asset', fn($q) =>
                $q->where('department_id', $deptId)
            )
            ->where('status', 'returned')
            ->whereNotNull('actual_return_date');

        if ($search) {
            $query->whereHas('submission', fn($q) =>
                $q->where('title', 'LIKE', "%{$search}%")
            )->orWhereHas('asset', fn($q) =>
                $q->where('asset_name', 'LIKE', "%{$search}%")
            );
        }

        // Group theo submission_id
        $grouped = $query
            ->orderBy('actual_return_date', 'desc')
            ->get()
            ->groupBy('submission_id');

        $data = $grouped->map(function ($items) use ($user) {
            $first      = $items->first();
            $submission = $first->submission;
            $borrower   = $first->borrower;
            $dept       = $first->asset?->department;

            return [
                'id'            => $first->submission_id,
                'code'          => 'BG-' . str_pad($first->submission_id, 4, '0', STR_PAD_LEFT),
                'title'         => $submission?->title ?? 'Không có tiêu đề',
                'from_dept'     => $dept?->name ?? '-',        // phòng sở hữu tài sản
                'to_dept'       => $borrower?->department?->name ?? '-', // phòng người mượn
                'handover_by'   => $user->name,                // trưởng phòng/nhân viên xem
                'handover_date' => $first->actual_return_date
                    ? Carbon::parse($first->actual_return_date)->format('H:i - d/m/Y')
                    : null,
                'items' => $items->map(fn($ar) => [
                    'name'          => $ar->asset?->asset_name ?? '-',
                    'qty'           => 1,
                    'is_consumable' => $ar->asset?->type === 'consumable',
                ])->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
