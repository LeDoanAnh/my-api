<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionCategory extends Model
{
    public function approvalConfigs() {
        return $this->hasMany(ApprovalConfig::class, 'category_id')->orderBy('step_order');
    }
}
