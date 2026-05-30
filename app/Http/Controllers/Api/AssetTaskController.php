<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use App\Models\AssetRequest;
use Carbon\Carbon;

class AssetTaskController extends Controller
{
    public function index(Request $request)
    {
        $deptId = $request->query('dept_id');
        $search = $request->query('search');
        $status = $request->query('status', 'approved');

        if (!$deptId) {
            return response()->json(['success' => false, 'message' => 'Thiếu dept_id'], 422);
        }

        $query = Submission::where('status', $status)
            ->whereHas('assetRequests.asset', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            })
            ->with([
                'creator.department',
                'assetRequests' => function ($q) use ($deptId) {
                    $q->whereHas('asset', function ($q2) use ($deptId) {
                        $q2->where('department_id', $deptId);
                    })->with(['asset', 'borrower.department']);
                },
                'approvalLogs' => function ($q) use ($deptId) {
                    $q->whereHas('approver', function ($q2) use ($deptId) {
                        $q2->where('department_id', $deptId);
                    })->with('approver.department');
                },
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        $submissions = $query->orderBy('updated_at', 'desc')->get();

        $data = $submissions->map(function ($submission) {
            $approvalLog = $submission->approvalLogs->first();

            return [
                'id'         => $submission->id,
                'title'      => $submission->title,
                'status'     => $submission->status,
                'date'       => Carbon::parse($submission->updated_at)->format('d/m/Y'),
                'sender'     => ($submission->creator->full_name ?? 'N/A')
                    . ' - ' . ($submission->creator->department->dept_name ?? 'N/A'),
                'item_count' => $submission->assetRequests->count(),

                'approved_by' => $approvalLog ? [
                    'id'          => $approvalLog->approver->id,
                    'name'        => $approvalLog->approver->full_name,
                    'dept'        => $approvalLog->approver->department->dept_name ?? 'N/A',
                    'action'      => $approvalLog->action,
                    'comment'     => $approvalLog->comment,
                    'approved_at' => Carbon::parse($approvalLog->created_at)->format('H:i d/m/Y'),
                ] : null,

                'assets' => $submission->assetRequests->map(fn($ar) => [
                    'asset_id'        => $ar->asset->id,
                    'asset_name'      => $ar->asset->asset_name,
                    'asset_code'      => $ar->asset->asset_code,
                    'unit'            => $ar->asset->unit,
                    'type'            => $ar->asset->type,
                    'status'          => $ar->status ?? 'pending',
                    'borrow_date'     => $ar->borrow_date
                        ? Carbon::parse($ar->borrow_date)->format('d/m/Y') : null,
                    'expected_return' => $ar->expected_return_date
                        ? Carbon::parse($ar->expected_return_date)->format('d/m/Y') : null,
                    'borrower'        => $ar->borrower ? [
                        'id'   => $ar->borrower->id,
                        'name' => $ar->borrower->full_name,
                        'dept' => $ar->borrower->department->dept_name ?? 'N/A',
                    ] : null,
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'total'   => $data->count(),
            'data'    => $data,
        ]);
    }
    public function show(Request $request, int $submissionId)
    {
        $deptId = $request->query('dept_id');

        if (!$deptId) {
            return response()->json(['success' => false, 'message' => 'Thiếu dept_id'], 422);
        }

        $submission = Submission::with([
            'creator.department',
            'assetRequests' => function ($q) use ($deptId) {
                $q->whereHas('asset', function ($q2) use ($deptId) {
                    $q2->where('department_id', $deptId);
                })->with(['asset', 'borrower.department']);
            },
            'approvalLogs' => function ($q) use ($deptId) {
                $q->whereHas('approver', function ($q2) use ($deptId) {
                    $q2->where('department_id', $deptId);
                })->with('approver.department');
            },
        ])->find($submissionId);

        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn'], 404);
        }

        $approvalLog = $submission->approvalLogs->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $submission->id,
                'title'      => $submission->title,
                'status'     => $submission->status,
                'date'       => Carbon::parse($submission->updated_at)->format('d/m/Y'),
                'sender'     => ($submission->creator->full_name ?? 'N/A')
                    . ' - ' . ($submission->creator->department->dept_name ?? 'N/A'),

                'approved_by' => $approvalLog ? [
                    'id'          => $approvalLog->approver->id,
                    'name'        => $approvalLog->approver->full_name,
                    'dept'        => $approvalLog->approver->department->dept_name ?? 'N/A',
                    'comment'     => $approvalLog->comment,
                    'approved_at' => Carbon::parse($approvalLog->created_at)->format('H:i d/m/Y'),
                ] : null,

                'assets' => $submission->assetRequests->map(fn($ar) => [
                    'asset_request_id' => $ar->id,
                    'asset_id'         => $ar->asset->id,
                    'asset_name'       => $ar->asset->asset_name,
                    'asset_code'       => $ar->asset->asset_code,
                    'unit'             => $ar->asset->unit,
                    'type'             => $ar->asset->type,        // returnable | consumable
                    'status'           => $ar->status ?? 'pending', // pending | borrowed | returned
                    'borrow_date'      => $ar->borrow_date
                        ? Carbon::parse($ar->borrow_date)->format('d/m/Y') : null,
                    'expected_return'  => $ar->expected_return_date
                        ? Carbon::parse($ar->expected_return_date)->format('d/m/Y') : null,
                    'borrower' => $ar->borrower ? [
                        'id'   => $ar->borrower->id,
                        'name' => $ar->borrower->full_name,
                        'dept' => $ar->borrower->department->dept_name ?? 'N/A',
                    ] : null,
                ]),
            ],
        ]);
    }

    // ── POST xác nhận bàn giao ───────────────────────────────────────────────
    public function handover(Request $request, int $submissionId)
    {
        $request->validate([
            'handler_id'        => 'required|exists:users,id',
            'asset_request_ids' => 'required|array',
            'asset_request_ids.*' => 'exists:asset_requests,id',
        ]);

        try {
            // Cập nhật status từng asset_request được bàn giao
            AssetRequest::whereIn('id', $request->asset_request_ids)
                ->where('submission_id', $submissionId)
                ->update([
                    'handler_id'  => $request->handler_id,
                    'status'      => 'borrowed',
                    'borrow_date' => now(),
                ]);

            // Kiểm tra tất cả asset của đơn này đã borrowed chưa
            $totalAssets    = AssetRequest::where('submission_id', $submissionId)->count();
            $borrowedAssets = AssetRequest::where('submission_id', $submissionId)
                ->where('status', 'borrowed')->count();

            $allHandedOver = $totalAssets > 0 && $borrowedAssets >= $totalAssets;

            return response()->json([
                'success'        => true,
                'message'        => $allHandedOver
                    ? 'Bàn giao hoàn tất toàn bộ vật tư!'
                    : 'Đã cập nhật trạng thái bàn giao.',
                'all_handed_over' => $allHandedOver,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage(),
            ], 500);
        }
    }
}
