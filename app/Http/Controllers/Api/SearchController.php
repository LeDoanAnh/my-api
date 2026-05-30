<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Asset;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query  = $request->query('q', '');
        $filter = $request->query('filter', 'all'); // all|submission|asset|user|department
        $userId = $request->query('user_id');

        if (strlen($query) < 1) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $results = [];

        // ── Tờ trình ───────────────────────────────────────────
        if (in_array($filter, ['all', 'submission'])) {
            $submissions = Submission::where('title', 'LIKE', "%{$query}%")
                ->where('creator_id', $userId)
                ->select('id', 'title', 'status', 'created_at')
                ->limit(5)
                ->get();

            foreach ($submissions as $s) {
                $results[] = [
                    'id'       => $s->id,
                    'title'    => $s->title,
                    'category' => 'submission',
                    'status'   => $this->mapStatus($s->status),
                    'ref_id'   => $s->id, // dùng để navigate
                ];
            }
        }

        // ── Thiết bị ───────────────────────────────────────────
        if (in_array($filter, ['all', 'asset'])) {
            $assets = Asset::where('asset_name', 'LIKE', "%{$query}%")
                ->orWhere('asset_code', 'LIKE', "%{$query}%")
                ->select('id', 'asset_name', 'asset_code', 'status', 'type')
                ->limit(5)
                ->get();

            foreach ($assets as $a) {
                $results[] = [
                    'id'       => $a->id,
                    'title'    => $a->asset_name,
                    'category' => 'asset',
                    'status'   => $a->status,
                    'ref_id'   => $a->id,
                ];
            }
        }

        // ── Nhân viên ──────────────────────────────────────────
        if (in_array($filter, ['all', 'user'])) {
            $users = User::where('full_name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->select('id', 'full_name', 'email')
                ->limit(5)
                ->get();

            foreach ($users as $u) {
                $results[] = [
                    'id'       => $u->id,
                    'title'    => $u->full_name,
                    'category' => 'user',
                    'status'   => $u->email ?? '-',
                    'ref_id'   => $u->id,
                ];
            }
        }

        // ── Phòng ban ──────────────────────────────────────────
        if (in_array($filter, ['all', 'department'])) {
            $depts = Department::where('dept_name', 'LIKE', "%{$query}%")
                ->select('id', 'dept_name')
                ->limit(5)
                ->get();

            foreach ($depts as $d) {
                $results[] = [
                    'id'       => $d->id,
                    'title'    => $d->dept_name,
                    'category' => 'department',
                    'status'   => 'Phòng ban',
                    'ref_id'   => $d->id,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    private function mapStatus(string $status): string
    {
        return match($status) {
            'pending'  => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            default    => 'Không xác định',
        };
    }
}
