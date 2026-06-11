<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionCategory extends Model
{
    protected $fillable = [
        'category_name',
        'description',
        'apply_for_dept_id',
        'status',
    ];

    public function approvalConfigs() {
        return $this->hasMany(ApprovalConfig::class, 'category_id')->orderBy('step_order');
    }

    public function applyForDept()
    {
        return $this->belongsTo(Department::class, 'apply_for_dept_id');
    }
}
