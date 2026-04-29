<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $table = 'submissions';

    // Cập nhật creator_id cho đúng với database bạn gửi
    protected $fillable = [
        'creator_id',
        'title',
        'content',
        'category_id',
        'location_id',
        'status',
        'start_time',
        'end_time'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Một tờ trình thuộc về một người tạo (User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
    public function assetRequests()
    {
        return $this->hasMany(AssetRequest::class, 'submission_id');
    }
}
