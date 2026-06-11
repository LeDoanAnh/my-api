<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = [
        'creator_id',
        'title',
        'content',
        'category_id',
        'status',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    /**
     * Người tạo tờ trình
     */
    public function user(): BelongsTo // <--- Đổi tên thành user
    {
        return $this->belongsTo(User::class, 'creator_id'); // Vẫn dùng cột creator_id
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Danh mục tờ trình (Mượn thiết bị, Xin kinh phí...)
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SubmissionCategory::class, 'category_id');
    }

    /**
     * Các yêu cầu mượn thiết bị đi kèm
     */
    public function assetRequests(): HasMany
    {
        return $this->hasMany(AssetRequest::class, 'submission_id');
    }

    /**
     * Lịch sử phê duyệtff
     */
    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'submission_id', 'id');
    }

    public function preApprovals(): HasMany
    {
        return $this->hasMany(SubmissionPreApproval::class, 'submission_id', 'id');
    }

    /**
     * Nội dung các bước thực hiện (Dùng để check target_dept_id như đã làm ở API)
     */
    public function stepContents(): HasMany
    {
        return $this->hasMany(SubmissionStepContent::class, 'submission_id');
    }

    // Nếu bạn dùng bảng trung gian cho địa điểm (như trong Seeder) thì nên dùng:
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'submission_locations', 'submission_id', 'location_id')
                    ->withPivot('start_time', 'end_time');
    }
    public function steps() {
    return $this->hasMany(SubmissionStepContent::class, 'submission_id', 'id')->orderBy('step_order');
    }

    public function logs() {
        return $this->hasMany(ApprovalLog::class, 'submission_id', 'id');
    }

    public function submissionLocations() {
        return $this->hasMany(SubmissionLocation::class, 'submission_id');
    }

}
