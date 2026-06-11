<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    protected $table = 'approval_log';

    protected $fillable = ['submission_id', 'step_content_id', 'approver_id', 'action', 'comment'];
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    public function stepContent(): BelongsTo
    {
        return $this->belongsTo(SubmissionStepContent::class, 'step_content_id');
    }

}
