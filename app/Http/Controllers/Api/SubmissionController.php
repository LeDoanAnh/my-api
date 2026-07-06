<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\SubmissionPreApproval;
use App\Models\Submission;
use App\Models\SubmissionStepContent;
use App\Models\User;
use App\Services\SubmissionApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class SubmissionController extends Controller
{
    /**
     * Lấy thống kê số lượng đơn (Dành cho Dashboard)
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        $stats = [
            'total_submissions'    => Submission::where('creator_id', $userId)->count(),
            'pending_submissions'  => Submission::where('creator_id', $userId)->where('status', 'pending')->count(),
            'rejected_submissions' => Submission::where('creator_id', $userId)->where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Lấy danh sách đơn gần đây
     */
    public function getRecentSubmissions(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        $recentSubmissions = Submission::where('creator_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'title'        => $item->title ?? 'Tờ trình không tiêu đề',
                    'status'       => $item->status,
                    'status_label' => $this->getStatusLabel($item->status),
                    'time'         => $item->created_at ? $item->created_at->diffForHumans() : 'Vừa xong',
                    'created_at_formatted' => $item->created_at ? $item->created_at->format('H:i A') : '--:--',
                ];
            });

        return response()->json($recentSubmissions);
    }

    /**
     * Danh sách tờ trình (Phân loại theo Tab và Role)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $keyword = $request->query('keyword');
        $type = $request->query('type', 'my_submissions');
        $perPage = $request->query('limit', 10);

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu tham số user_id'], 400);
        }

        $user = User::with(['roles'])->find($userId);
        if (!$user) return response()->json(['message' => 'User không tồn tại'], 404);

        $userDeptId = $user->department_id;
        $isLeader = $user->roles->contains('id', 3); // Trưởng phòng
        $isStaff = $user->roles->contains('id', 4);  // Cán bộ nghiệp vụ

        $query = Submission::with(['category', 'creator', 'approvalLogs.approver', 'preApprovals.staff']);

        // 1. TÌM KIẾM
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $cleanedKeyword = str_replace(['TD-', 'DV-'], '', $keyword);
                $q->where('title', 'like', "%$keyword%")
                  ->orWhere('id', 'like', "%$cleanedKeyword%");
            });
        }

        // 2. PHÂN LOẠI LOGIC THEO TYPE
        if ($type === 'pending_approval') {
            // Chỉ Role 3 và 4 mới được vào Tab này
            if (!$isLeader && !$isStaff) {
                return response()->json(['message' => 'Bạn không có quyền truy cập mục này'], 403);
            }

            $query->where(function ($q) use ($userId, $userDeptId, $isLeader, $isStaff) {

                // TRƯỜNG HỢP: TRƯỞNG PHÒNG (ROLE 3)
                if ($isLeader) {
                    // Xem đơn CHỜ của phòng mình để ký
                    $q->where(function ($sub) use ($userDeptId) {
                        $sub->where('status', 'pending')
                            ->whereExists(function ($current) use ($userDeptId) {
                                $current->select(DB::raw(1))
                                    ->from('submission_step_contents as current_steps')
                                    ->whereColumn('current_steps.submission_id', 'submissions.id')
                                    ->where('current_steps.target_dept_id', $userDeptId)
                                    ->where(function ($preCheck) use ($userDeptId) {
                                        $preCheck->whereNotExists(function ($staff) use ($userDeptId) {
                                            $staff->select(DB::raw(1))
                                                ->from('users as pre_staff')
                                                ->join('role_user as pre_staff_roles', 'pre_staff_roles.user_id', '=', 'pre_staff.id')
                                                ->where('pre_staff.department_id', $userDeptId)
                                                ->where('pre_staff_roles.role_id', 4);
                                        })
                                        ->orWhereExists(function ($signed) {
                                            $signed->select(DB::raw(1))
                                                ->from('submission_pre_approvals as pre_logs')
                                                ->whereColumn('pre_logs.submission_id', 'submissions.id')
                                                ->whereColumn('pre_logs.step_content_id', 'current_steps.id')
                                                ->where('pre_logs.action', 'signed');
                                        });
                                    })
                                    ->whereNotExists(function ($doneCurrent) {
                                        $doneCurrent->select(DB::raw(1))
                                            ->from('approval_log as current_logs')
                                            ->leftJoin('users as current_approvers', 'current_approvers.id', '=', 'current_logs.approver_id')
                                            ->whereColumn('current_logs.submission_id', 'submissions.id')
                                            ->where(function ($logMatch) {
                                                $logMatch->whereColumn('current_logs.step_content_id', 'current_steps.id')
                                                    ->orWhere(function ($legacyLog) {
                                                        $legacyLog->whereNull('current_logs.step_content_id')
                                                            ->whereColumn('current_approvers.department_id', 'current_steps.target_dept_id');
                                                    });
                                            });
                                    })
                                    ->whereNotExists(function ($previous) {
                                        $previous->select(DB::raw(1))
                                            ->from('submission_step_contents as previous_steps')
                                            ->whereColumn('previous_steps.submission_id', 'submissions.id')
                                            ->where(function ($order) {
                                                $order->whereColumn('previous_steps.step_order', '<', 'current_steps.step_order')
                                                    ->orWhere(function ($sameOrder) {
                                                        $sameOrder->whereColumn('previous_steps.step_order', 'current_steps.step_order')
                                                            ->whereColumn('previous_steps.id', '<', 'current_steps.id');
                                                    });
                                            })
                                            ->whereNotExists(function ($approvedPrevious) {
                                                $approvedPrevious->select(DB::raw(1))
                                                    ->from('approval_log as previous_logs')
                                                    ->leftJoin('users as previous_approvers', 'previous_approvers.id', '=', 'previous_logs.approver_id')
                                                    ->whereColumn('previous_logs.submission_id', 'submissions.id')
                                                    ->where('previous_logs.action', 'approved')
                                                    ->where(function ($logMatch) {
                                                        $logMatch->whereColumn('previous_logs.step_content_id', 'previous_steps.id')
                                                            ->orWhere(function ($legacyLog) {
                                                                $legacyLog->whereNull('previous_logs.step_content_id')
                                                                    ->whereColumn('previous_approvers.department_id', 'previous_steps.target_dept_id');
                                                            });
                                                    });
                                            });
                                    });
                            });
                    })
                    // HOẶC xem những đơn mình ĐÃ ký (lịch sử)
                    ->orWhereHas('approvalLogs', function ($log) use ($userId) {
                        $log->where('approver_id', $userId);
                    });
                }

                // TRƯỜNG HỢP: CÁN BỘ (ROLE 4)
                elseif ($isStaff) {
                    // CHỈ xem đơn của phòng mình VÀ trạng thái KHÁC pending (đã có kết quả)
                    $q->where(function ($staffQuery) use ($userId, $userDeptId) {
                        $staffQuery->where(function ($pending) use ($userDeptId) {
                            $pending->where('status', 'pending')
                                ->whereExists(function ($current) use ($userDeptId) {
                                    $current->select(DB::raw(1))
                                        ->from('submission_step_contents as current_steps')
                                        ->whereColumn('current_steps.submission_id', 'submissions.id')
                                        ->where('current_steps.target_dept_id', $userDeptId)
                                        ->whereNotExists(function ($doneCurrent) {
                                            $doneCurrent->select(DB::raw(1))
                                                ->from('approval_log as current_logs')
                                                ->leftJoin('users as current_approvers', 'current_approvers.id', '=', 'current_logs.approver_id')
                                                ->whereColumn('current_logs.submission_id', 'submissions.id')
                                                ->where(function ($logMatch) {
                                                    $logMatch->whereColumn('current_logs.step_content_id', 'current_steps.id')
                                                        ->orWhere(function ($legacyLog) {
                                                            $legacyLog->whereNull('current_logs.step_content_id')
                                                                ->whereColumn('current_approvers.department_id', 'current_steps.target_dept_id');
                                                        });
                                                });
                                        })
                                        ->whereNotExists(function ($previous) {
                                            $previous->select(DB::raw(1))
                                                ->from('submission_step_contents as previous_steps')
                                                ->whereColumn('previous_steps.submission_id', 'submissions.id')
                                                ->where(function ($order) {
                                                    $order->whereColumn('previous_steps.step_order', '<', 'current_steps.step_order')
                                                        ->orWhere(function ($sameOrder) {
                                                            $sameOrder->whereColumn('previous_steps.step_order', 'current_steps.step_order')
                                                                ->whereColumn('previous_steps.id', '<', 'current_steps.id');
                                                        });
                                                })
                                                ->whereNotExists(function ($approvedPrevious) {
                                                    $approvedPrevious->select(DB::raw(1))
                                                        ->from('approval_log as previous_logs')
                                                        ->leftJoin('users as previous_approvers', 'previous_approvers.id', '=', 'previous_logs.approver_id')
                                                        ->whereColumn('previous_logs.submission_id', 'submissions.id')
                                                        ->where('previous_logs.action', 'approved')
                                                        ->where(function ($logMatch) {
                                                            $logMatch->whereColumn('previous_logs.step_content_id', 'previous_steps.id')
                                                                ->orWhere(function ($legacyLog) {
                                                                    $legacyLog->whereNull('previous_logs.step_content_id')
                                                                        ->whereColumn('previous_approvers.department_id', 'previous_steps.target_dept_id');
                                                                });
                                                        });
                                                });
                                        });
                                });
                        })
                        ->orWhereHas('preApprovals', function ($pre) use ($userId) {
                            $pre->where('staff_id', $userId);
                        });
                    });
                }
            });
        } else {
            // TAB "CỦA TÔI"
            $query->where('creator_id', $userId);
            $status = $request->query('status');
            if ($status) $query->where('status', $status);
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($submissions->items())->map(function ($item) use ($type, $userId, $isStaff) {
            $myAction = $item->approvalLogs->where('approver_id', $userId)->last();
            $myPreAction = $item->preApprovals->where('staff_id', $userId)->last();
            $lastPreApproval = $item->preApprovals->where('action', 'signed')->last()
                ?: $item->preApprovals->last();
            $lastLog = $item->approvalLogs->whereIn('action', ['approved', 'rejected'])->last();

            $approverName = $lastLog && $lastLog->approver ? $lastLog->approver->full_name : null;
            $displayStatus = $this->getStatusLabel($item->status);
            $statusCode = $item->status;

            // Xử lý nhãn hiển thị cho Tab duyệt đơn
            if ($type === 'pending_approval') {
                if ($isStaff && $myPreAction) {
                    $displayStatus = $myPreAction->action === 'signed' ? "Tôi đã ký nháy" : "Tôi đã báo sửa";
                    $statusCode = $myPreAction->action;
                    $approverName = "Báº¡n";
                } elseif ($isStaff && $item->status === 'pending') {
                    $displayStatus = "Chờ tôi ký nháy";
                    $statusCode = "waiting_pre_approval";
                } elseif ($item->status === 'pending' && !$myAction) {
                    $displayStatus = "Chờ tôi ký";
                    $statusCode = "waiting_for_me";
                } elseif ($myAction) {
                    $displayStatus = $myAction->action === 'approved' ? "Tôi đã duyệt" : "Tôi đã từ chối";
                    $statusCode = $myAction->action;
                    $approverName = "Bạn";
                }
            }

            return [
                'id' => $item->id,
                'submission_code' => ($item->category_id == 2 ? 'TD-' : 'DV-') . (9900 + $item->id),
                'title' => $item->title ?? 'Không tiêu đề',
                'date' => $item->created_at ? $item->created_at->toIso8601String() : null,
                'category_name' => $item->category->category_name ?? 'N/A',
                'status' => $displayStatus,
                'status_code' => $statusCode,
                'creator_name' => $item->creator->full_name ?? 'Ẩn danh',
                'approver_name' => $approverName,
                'pre_approval_status' => $lastPreApproval?->action,
                'pre_approver_name' => $lastPreApproval?->staff?->full_name,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
            'total'   => $submissions->total(),
        ]);
    }

    /**
     * Chuyển đổi mã status sang nhãn tiếng Việt
     */
    private function getStatusLabel(?string $status): string
    {
        $labels = [
            'pending'  => 'Chờ phê duyệt',
            'approved' => 'Đã đồng ý',
            'rejected' => 'Bị từ chối',
        ];

        return $labels[$status] ?? 'Không xác định';
    }
    /**
 * Lấy chi tiết một tờ trình (Dùng Eloquent Model)
 */
    public function show($id): JsonResponse
    {
        try {
            $submission = Submission::with([
                'category',
                'creator.department',
                'steps.department',
                'steps.approvalLogs.approver.department',
                'steps.preApprovals.staff.department',
                'assetRequests.asset.department',
                'submissionLocations.location.department',
            ])->find($id);

            if (!$submission) {
                return response()->json(['message' => 'Khong tim thay don'], 404);
            }

            $detail = app(\App\Services\SubmissionDetailService::class)->buildDetail($submission);

            return response()->json(array_merge([
                'id' => $submission->id,
                'code' => ($submission->category_id == 2 ? 'TD-' : 'DV-') . (9900 + $submission->id),
                'title' => $submission->title,
                'content' => $submission->content,
                'status' => $submission->status,
            ], $detail));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Loi: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function pdf(int $id): Response
    {
        $submission = Submission::with([
            'category',
            'creator.department',
            'steps.department',
            'steps.approvalLogs.approver',
            'steps.preApprovals.staff',
            'assetRequests.asset',
            'submissionLocations.location',
        ])->findOrFail($id);

        $code = ($submission->category_id == 2 ? 'TD-' : 'DV-') . (9900 + $submission->id);
        $fileName = "to-trinh-{$code}.pdf";

        return Pdf::loadView('pdf.submission', [
            'submission' => $submission,
            'code' => $code,
            'statusLabel' => $this->getStatusLabel($submission->status),
        ])->setPaper('a4')->stream($fileName);
    }
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'workflow_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'departments' => 'required|string',
            'creator_id' => 'required|integer',
        ]);

        $departmentsData = json_decode($request->departments, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Du lieu phong ban khong hop le: ' . json_last_error_msg(),
            ], 422);
        }

        if (empty($departmentsData) || !is_array($departmentsData)) {
            return response()->json([
                'success' => false,
                'message' => 'Danh sach phong ban khong duoc rong.',
            ], 422);
        }

        $submission = Submission::find($id);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay to trinh.',
            ], 404);
        }

        if ((int) $submission->creator_id !== (int) $request->creator_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ban khong co quyen cap nhat to trinh nay.',
            ], 403);
        }

        $hasRevisionRequested = SubmissionPreApproval::where('submission_id', $id)
            ->where('action', 'revision_requested')
            ->exists();
        $hasRejectedDecision = ApprovalLog::where('submission_id', $id)
            ->where('action', 'rejected')
            ->exists();

        if ($submission->status !== 'rejected' && !$hasRevisionRequested && !$hasRejectedDecision) {
            return response()->json([
                'success' => false,
                'message' => 'Chi co the cap nhat khi to trinh da bi tu choi.',
            ], 409);
        }

        $stepOrderCol = 'step_oder';
        $cols = DB::select("SHOW COLUMNS FROM submission_step_contents LIKE 'step_order%'");
        if (!empty($cols)) {
            $stepOrderCol = $cols[0]->Field;
        }

        DB::beginTransaction();

        try {
            DB::table('submission_step_contents')->where('submission_id', $id)->delete();
            DB::table('approval_log')->where('submission_id', $id)->delete();
            DB::table('submission_pre_approvals')->where('submission_id', $id)->delete();
            DB::table('asset_requests')->where('submission_id', $id)->delete();
            DB::table('submission_locations')->where('submission_id', $id)->delete();

            $submission->update([
                'title' => $request->title,
                'content' => $request->description ?? '',
                'category_id' => (int) $request->workflow_id,
                'status' => 'pending',
                'start_time' => $request->start_date,
                'end_time' => $request->end_date,
                'updated_at' => now(),
            ]);

            foreach ($departmentsData as $dept) {
                $deptName = $dept['dept_name'] ?? 'Phong ban chung';
                $noteText = $dept['note'] ?? '';
                $priority = (int) ($dept['priority'] ?? 99);
                $opinionOnly = (bool) ($dept['opinion_only'] ?? false);

                $deptId = isset($dept['dept_id']) ? (int) $dept['dept_id'] : 0;
                $targetDeptId = $deptId > 0 ? $deptId : null;

                if ($targetDeptId === null && !empty($deptName) && $deptName !== 'Dia diem') {
                    $dbDept = DB::table('departments')
                        ->where('dept_name', $deptName)
                        ->first();
                    $targetDeptId = $dbDept?->id;
                }

                if ($targetDeptId !== null) {
                    $stepData = [
                        'submission_id' => $id,
                        'target_dept_id' => $targetDeptId,
                        'content_text' => $noteText,
                        'created_at' => now(),
                    ];
                    $stepData[$stepOrderCol] = $priority;

                    DB::table('submission_step_contents')->insert($stepData);
                }

                if ($opinionOnly || empty($dept['items']) || !is_array($dept['items'])) {
                    continue;
                }

                foreach ($dept['items'] as $item) {
                    $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : null;
                    $quantity = (int) ($item['quantity'] ?? 1);
                    $timeInfo = $item['time_info'] ?? '';
                    $itemType = $item['type'] ?? 'returnable';

                    if ($itemType === 'location') {
                        $locStartTime = !empty($item['start_time'])
                            ? $item['start_time']
                            : $request->start_date;

                        $locEndTime = !empty($item['end_time'])
                            ? $item['end_time']
                            : $request->end_date;

                        DB::table('submission_locations')->insert([
                            'submission_id' => $id,
                            'location_id' => $entityId,
                            'start_time' => $locStartTime,
                            'end_time' => $locEndTime,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $expectedBorrowDate = !empty($item['expected_borrow_date'])
                            ? $item['expected_borrow_date']
                            : $request->start_date;

                        $expectedReturnDate = !empty($item['expected_return_date'])
                            ? $item['expected_return_date']
                            : null;

                        if ($itemType === 'returnable' && $expectedReturnDate === null) {
                            $expectedReturnDate = $request->end_date;
                        }

                        DB::table('asset_requests')->insert([
                            'submission_id' => $id,
                            'asset_id' => $entityId,
                            'borrower_id' => $submission->creator_id,
                            'handler_id' => null,
                            'expected_borrow_date' => $expectedBorrowDate,
                            'borrow_date' => null,
                            'expected_return_date' => $expectedReturnDate,
                            'actual_return_date' => null,
                            'note' => "SL: {$quantity} | Chi tiet: {$timeInfo}",
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            $uploadedFiles = $request->file('attachments') ?? [];
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $path = $file->store('uploads/submissions', 'public');
                DB::table('submission_attachments')->insert([
                    'submission_id' => $id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            app(SubmissionApprovalFlowService::class)->dispatchSubmissionById($id);

            return response()->json([
                'success' => true,
                'message' => 'To trinh da duoc cap nhat thanh cong.',
                'submission_id' => $id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SubmissionController@update] ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Loi he thong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        // ── Validate ──────────────────────────────────────────────────────────
        $request->validate([
            'title'       => 'required|string|max:255',
            'workflow_id' => 'required|integer',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'departments' => 'required|string',
            'creator_id'  => 'required|integer',
        ]);

        // ── Giải mã JSON departments ──────────────────────────────────────────
        $departmentsData = json_decode($request->departments, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu phòng ban không hợp lệ: ' . json_last_error_msg(),
            ], 422);
        }

        if (empty($departmentsData) || !is_array($departmentsData)) {
            return response()->json([
                'success' => false,
                'message' => 'Danh sách phòng ban không được rỗng.',
            ], 422);
        }

        $creatorId = (int) $request->creator_id;

        // ── Detect tên cột step_order 1 lần trước transaction ────────────────
        $stepOrderCol = 'step_oder'; // typo trong schema gốc
        $cols = DB::select("SHOW COLUMNS FROM submission_step_contents LIKE 'step_order%'");
        if (!empty($cols)) {
            $stepOrderCol = $cols[0]->Field;
        }

        DB::beginTransaction();

        try {
            // ── Bảng 1: submissions ───────────────────────────────────────────
            $submissionId = DB::table('submissions')->insertGetId([
                'title'       => $request->title,
                'content'     => $request->description ?? '',
                'creator_id'  => $creatorId,
                'category_id' => (int) $request->workflow_id,
                'status'      => 'pending',
                'start_time'  => $request->start_date,
                'end_time'    => $request->end_date,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // ── Vòng lặp từng nhóm phòng ban / địa điểm ──────────────────────
            foreach ($departmentsData as $dept) {
                $deptName    = $dept['dept_name']    ?? 'Phòng ban chung';
                $noteText    = $dept['note']         ?? '';
                $priority    = (int) ($dept['priority'] ?? 99);
                $opinionOnly = (bool) ($dept['opinion_only'] ?? false);

                // ── Xác định target_dept_id ───────────────────────────────────
                $deptId       = isset($dept['dept_id']) ? (int) $dept['dept_id'] : 0;
                $targetDeptId = $deptId > 0 ? $deptId : null;

                // Fallback: lookup theo tên nếu không có dept_id
                if ($targetDeptId === null && !empty($deptName) && $deptName !== 'Địa điểm') {
                    $dbDept = DB::table('departments')
                        ->where('dept_name', $deptName)
                        ->first();
                    $targetDeptId = $dbDept?->id;

                    if ($targetDeptId === null) {
                        Log::warning("[Submission #{$submissionId}] Không tìm thấy dept cho: '{$deptName}'");
                    }
                }

                // ── Bảng 2: submission_step_contents ─────────────────────────
                // Insert khi có target_dept_id — bao gồm cả phòng "chỉ xin ý kiến"
                // (opinion_only = true, items rỗng).
                if ($targetDeptId !== null) {
                    $stepData = [
                        'submission_id'  => $submissionId,
                        'target_dept_id' => $targetDeptId,
                        'content_text'   => $noteText,
                        'created_at'     => now(),
                    ];
                    $stepData[$stepOrderCol] = $priority;

                    DB::table('submission_step_contents')->insert($stepData);
                }

                // ── Nếu opinion_only hoặc không có items → bỏ qua phần dưới ──
                // Phòng xin ý kiến đã được ghi vào step_contents ở trên.
                // Không cần insert asset_requests hay submission_locations.
                if ($opinionOnly || empty($dept['items']) || !is_array($dept['items'])) {
                    continue;
                }

                // ── Bảng 3 & 4: items ─────────────────────────────────────────
                foreach ($dept['items'] as $item) {
                    $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : null;
                    $quantity  = (int) ($item['quantity'] ?? 1);
                    $timeInfo  = $item['time_info']  ?? '';
                    $itemType  = $item['type']       ?? 'returnable';

                    if ($itemType === 'location') {
                        // ── Bảng 3: submission_locations ─────────────────────
                        //
                        // [FIX VẤN ĐỀ 1]
                        // Flutter gửi start_time/end_time cho MỖI SLOT địa điểm.
                        // Ưu tiên dùng giá trị slot; fallback về ngày tờ trình.
                        //
                        $locStartTime = !empty($item['start_time'])
                            ? $item['start_time']
                            : $request->start_date;

                        $locEndTime = !empty($item['end_time'])
                            ? $item['end_time']
                            : $request->end_date;

                        if ($entityId === null) {
                            Log::warning(
                                "[Submission #{$submissionId}] location entity_id null" .
                                " | name: {$item['name']} | time_info: {$timeInfo}"
                            );
                        }

                        DB::table('submission_locations')->insert([
                            'submission_id' => $submissionId,
                            'location_id'   => $entityId,
                            'start_time'    => $locStartTime,  // ← giờ thực tế từ Flutter
                            'end_time'      => $locEndTime,    // ← giờ thực tế từ Flutter
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    } else {
                        $expectedBorrowDate = !empty($item['expected_borrow_date'])
                            ? $item['expected_borrow_date']
                            : $request->start_date;

                        $expectedReturnDate = !empty($item['expected_return_date'])
                            ? $item['expected_return_date']
                            : null;

                        if ($itemType === 'returnable' && $expectedReturnDate === null) {
                            $expectedReturnDate = $request->end_date;
                        }
                        // ── Bảng 4: asset_requests ───────────────────────────
                        DB::table('asset_requests')->insert([
                            'submission_id'        => $submissionId,
                            'asset_id'             => $entityId,
                            'borrower_id'          => $creatorId,
                            'handler_id'           => null,
                            'expected_borrow_date' => $expectedBorrowDate,
                            'borrow_date'          => null,
                            'expected_return_date' => $expectedReturnDate,
                            'actual_return_date'   => null,
                            'note'                 => "SL: {$quantity} | Chi tiết: {$timeInfo}",
                            'created_at'           => now(),
                        ]);
                    }
                }
            }

            // ── Bảng 5: submission_attachments ───────────────────────────────
            $uploadedFiles = $request->file('attachments') ?? [];
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $file) {
                if (!$file || !$file->isValid()) continue;

                $path = $file->store('uploads/submissions', 'public');
                DB::table('submission_attachments')->insert([
                    'submission_id' => $submissionId,
                    'file_path'     => $path,
                    'file_name'     => $file->getClientOriginalName(),
                    'file_size'     => $file->getSize(),
                    'file_type'     => $file->getClientOriginalExtension(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            DB::commit();

            app(SubmissionApprovalFlowService::class)->dispatchSubmissionById($submissionId);

            return response()->json([
                'success'       => true,
                'message'       => 'Tờ trình đã được khởi tạo thành công.',
                'submission_id' => $submissionId,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SubmissionController@store] ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
            ], 500);
        }
    }
}

