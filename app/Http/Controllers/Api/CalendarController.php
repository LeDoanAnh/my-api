<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function getCalendarEvents(Request $request)
    {
        $month = $request->query('month', Carbon::now()-> month);
        $year = $request->query('year', Carbon::now()->year);

        $submissions = Submission::with(['location','assetRequests.asset'])
        ->whereYear('start_time',$year)
        ->whereMonth('start_time',$month)
        ->where('status', '!=', 'rejected')
        ->get();

        $formattedEvents = [];

        foreach ($submissions as $sub){
            $dateKey = Carbon::parse($sub->start_time)->toDateString();
            $timeRange = Carbon::parse($sub->start_time)->format('H:i');

            if($sub->location){
                $formattedEvents[$dateKey][] = [
                    'title' => $sub->location->location_name,
                    'type' => 'Địa điểm',
                    'status' => $sub->status == 'approved' ? 'Đã phê duyệt' : 'Đang phê duyệt',
                    'time' => $timeRange,
                    'color' => $this->getColorByStatus($sub->status)
                ];
            }
            foreach ($sub->assetRequests as $request) {
                $formattedEvents[$dateKey][] = [
                    'title' => $request->asset->asset_name,
                    'type' => 'Vật dụng',
                    'status' => $sub->status == 'approved' ? 'Đã phê duyệt' : 'Đang phê duyệt',
                    'time' => $timeRange,
                    'color' => $this->getColorByStatus($sub->status)
                ];
            }
        }
        return response()->json([
            'success' => true,
            'data' => (object)$formattedEvents
        ]);
    }
    private function getColorByStatus($status) {
        return match($status) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'error',
            default => 'grey'
        };
    }
}
