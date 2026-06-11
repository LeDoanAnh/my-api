<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionPreApproval extends Model
{
    protected $table = 'submission_pre_approvals';

    protected $fillable = [
        'submission_id',
        'step_content_id',
        'staff_id',
        'action',
        'comment',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    public function stepContent(): BelongsTo
    {
        return $this->belongsTo(SubmissionStepContent::class, 'step_content_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionPreApprovalAttachment::class, 'pre_approval_id');
    }
}
