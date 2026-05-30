<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Asset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        // Sử dụng with() để lấy kèm dữ liệu phòng ban
        $query = Asset::with(['department' => function($q) {
            $q->select('id', 'dept_name');
        }]);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('asset_name', 'like', "%{$search}%")
                ->orWhere('asset_code', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }
    public function show($id)
    {
        // Eager loading để tối ưu hiệu suất (giảm truy vấn N+1)
        $asset = Asset::with([
            'department',
            'currentRequest.borrower.department', // Lấy thông tin người mượn và khoa của họ
            'currentRequest.handler',
            'history.borrower'
        ])->find($id);

        if (!$asset) {
            return response()->json(['message' => 'Không tìm thấy vật tư'], 404);
        }

        return response()->json([
            'id'            => $asset->id,
            'asset_name'    => $asset->asset_name,
            'asset_code'    => $asset->asset_code,
            'unit'          => $asset->unit,
            'status'        => $asset->status_label,
            'dept_name'     => $asset->department?->dept_name ?? 'N/A', // Sửa name thành dept_name
            'is_consumable' => $asset->type === 'consumable',

            'current_request' => $asset->currentRequest ? [
                // Sửa name thành full_name và dept_name
                'borrower'        => ($asset->currentRequest->borrower?->full_name ?? 'N/A') . " - " . ($asset->currentRequest->borrower?->department?->dept_name ?? 'N/A'),
                'handler'         => $asset->currentRequest->handler?->full_name ?? 'Chờ duyệt',

                // Sửa thành borrow_date và expected_return_date theo sơ đồ
                'borrow_date'     => $asset->currentRequest->borrow_date?->format('d/m/Y'),
                'expected_return' => $asset->currentRequest->expected_return_date?->format('d/m/Y'),
                'note'            => $asset->currentRequest->note,
            ] : null,

            'history' => $asset->history->map(function ($h) {
                return [
                    'user'   => $h->borrower?->full_name ?? 'N/A', // Sửa name thành full_name
                    'action' => $h->borrow_date ? "Đã hoàn trả" : "Đang mượn",
                    'date'   => $h->created_at->format('d/m/Y'),
                ];
            }),
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'description'=> 'required|string',
            'unit'       => 'required|string|max:50',
            'asset_type' => 'required|in:returnable,consumable',
            'dept_id'    => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            Asset::create([
                'asset_name'    => $request->name,
                'asset_code'    => $request->description,
                'unit'          => $request->unit,
                'type'          => $request->asset_type,
                'department_id' => $request->dept_id,
                'status'        => 'ready',
            ]);

            return response()->json(['success' => true, 'message' => 'Tạo vật tư thành công!'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}
