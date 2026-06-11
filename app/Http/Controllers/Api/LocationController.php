<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(Request $request) {
    // Load thêm quan hệ department để lấy tên đơn vị quản lý
        $query = Location::with('department:id,dept_name');

        if ($request->has('search')) {
            $query->where('location_name', 'like', "%{$request->search}%");
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    /**
     * Lấy chi tiết địa điểm bao gồm sự kiện hiện tại và sắp tới
     */
    public function show(int $id)
    {
        $now = Carbon::now();

        // 1. Lấy thông tin địa điểm và phòng ban quản lý
        $location = Location::with(['department:id,dept_name,location_desc'])
            ->find($id);

        if (!$location) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy địa điểm'], 404);
        }

        // 2. Lấy sự kiện đang diễn ra (Current Booking)
        // Dựa trên bảng submission_locations kết nối với submissions
        $currentBooking = $location->submissionLocations()
            ->whereHas('submission', function($q) {
                $q->where('status', 'approved'); // Chỉ lấy tờ trình đã duyệt
            })
            ->with(['submission.creator.department']) // Lấy thông tin người tạo và đơn vị của họ
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->first();

        // 3. Lấy danh sách sự kiện sắp tới
        $upcomingEvents = $location->submissionLocations()
            ->whereHas('submission', function($q) {
                $q->where('status', 'approved');
            })
            ->with('submission:id,title')
            ->where('start_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->limit(5)
            ->get();

        // 4. Format dữ liệu chuẩn Map cho Flutter
        return response()->json([
            'success' => true,
            'id' => $location->id,
            'location_name' => $location->location_name,
            'capacity' => $location->capacity,
            'status' => $currentBooking ? 'Đang sử dụng' : 'Trống',
            'dept_name' => $location->department->dept_name ?? 'N/A',
            'location_desc' => $location->department->location_desc ?? '',
            "address"=> $location->address?? "Không xác định",
            'current_booking' =>  $currentBooking ? [
                'title' => $currentBooking->submission->title,
                'time' => $currentBooking->start_time->format('H:i') . ' - ' . $currentBooking->end_time->format('H:i'),
                'date' => $currentBooking->start_time->format('d/m/Y'),
                'organizer' => $currentBooking->submission->creator->department->dept_name ?? 'N/A'
            ] : null,
            'upcoming_events' => $upcomingEvents->map(function($event) {
                return [
                    'title' => $event->submission->title,
                    'date' => $event->start_time->format('d/m/Y')
                ];
            })
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'capacity' => 'required|string',
            'address'  => 'required|string|max:255',
            'dept_id'  => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            Location::create([
                'location_name' => $request->name,
                'capacity'      => $request->capacity,
                'address'       => $request->address,
                'department_id' => $request->dept_id,
                'status'        => 'active',
            ]);

            return response()->json(['success' => true, 'message' => 'Tạo địa điểm thành công!'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'capacity' => 'required|string',
            'address'  => 'required|string|max:255',
            'dept_id'  => 'required|exists:departments,id',
            'status'   => 'required|in:active,inactive,available',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $location->update([
            'location_name' => $request->name,
            'capacity'      => $request->capacity,
            'address'       => $request->address,
            'department_id' => $request->dept_id,
            'status'        => $request->status === 'available' ? 'active' : $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật địa điểm thành công.',
            'data' => $location,
        ]);
    }
}
