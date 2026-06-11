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
        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $submissions = Submission::with(['submissionLocations.location', 'assetRequests.asset'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($month, $year) {
                $query->where(function ($subQuery) use ($month, $year) {
                    $subQuery->whereYear('start_time', $year)
                        ->whereMonth('start_time', $month);
                })
                    ->orWhereHas('submissionLocations', function ($locationQuery) use ($month, $year) {
                        $locationQuery->whereYear('start_time', $year)
                            ->whereMonth('start_time', $month);
                    })
                    ->orWhereHas('assetRequests', function ($assetQuery) use ($month, $year) {
                        $assetQuery->whereYear('expected_borrow_date', $year)
                            ->whereMonth('expected_borrow_date', $month);
                    });
            })
            ->get();

        $formattedEvents = [];

        foreach ($submissions as $submission) {
            $this->appendLocationEvents($formattedEvents, $submission, $month, $year);
            $this->appendAssetEvents($formattedEvents, $submission, $month, $year);
        }

        return response()->json([
            'success' => true,
            'data' => (object) $formattedEvents,
        ]);
    }

    private function appendLocationEvents(array &$formattedEvents, Submission $submission, int $month, int $year): void
    {
        foreach ($submission->submissionLocations as $submissionLocation) {
            if (!$submissionLocation->location || !$submissionLocation->start_time) {
                continue;
            }

            $startTime = Carbon::parse($submissionLocation->start_time);
            if (!$this->isInRequestedMonth($startTime, $month, $year)) {
                continue;
            }

            $timeRange = $startTime->format('H:i');
            if ($submissionLocation->end_time) {
                $timeRange .= ' - ' . Carbon::parse($submissionLocation->end_time)->format('H:i');
            }

            $formattedEvents[$startTime->toDateString()][] = [
                'title' => $submissionLocation->location->location_name,
                'type' => 'Địa điểm',
                'status' => $this->getStatusLabel($submission->status),
                'time' => $timeRange,
                'color' => $this->getColorByStatus($submission->status),
            ];
        }
    }

    private function appendAssetEvents(array &$formattedEvents, Submission $submission, int $month, int $year): void
    {
        foreach ($submission->assetRequests as $assetRequest) {
            if (!$assetRequest->asset) {
                continue;
            }

            $eventDate = $assetRequest->expected_borrow_date
                ? Carbon::parse($assetRequest->expected_borrow_date)
                : ($submission->start_time ? Carbon::parse($submission->start_time) : null);

            if (!$eventDate || !$this->isInRequestedMonth($eventDate, $month, $year)) {
                continue;
            }

            $formattedEvents[$eventDate->toDateString()][] = [
                'title' => $assetRequest->asset->asset_name,
                'type' => 'Vật tư',
                'status' => $this->getStatusLabel($submission->status),
                'time' => $eventDate->format('H:i'),
                'color' => $this->getColorByStatus($submission->status),
            ];
        }
    }

    private function isInRequestedMonth(Carbon $date, int $month, int $year): bool
    {
        return (int) $date->month === $month && (int) $date->year === $year;
    }

    private function getStatusLabel(string $status): string
    {
        return $status === 'approved' ? 'Đã phê duyệt' : 'Đang phê duyệt';
    }

    private function getColorByStatus($status)
    {
        return match ($status) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'error',
            default => 'grey',
        };
    }
}
