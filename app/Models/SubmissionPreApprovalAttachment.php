<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionPreApprovalAttachment extends Model
{
    protected $table = 'submission_pre_approval_attachments';

    protected $fillable = [
        'pre_approval_id',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
    ];

    public function preApproval(): BelongsTo
    {
        return $this->belongsTo(SubmissionPreApproval::class, 'pre_approval_id');
    }
}
