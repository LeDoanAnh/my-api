<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionStepContent extends Model
{
    protected $table = 'submission_step_contents';
    protected $fillable = ['submission_id', 'step_order', 'target_dept_id', 'content_text'];

    public function department() {
        return $this->belongsTo(Department::class, 'target_dept_id', 'id');
    }
    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
