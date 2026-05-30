<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalConfig extends Model
{
    protected $table = 'approval_configs';

    public function targetRole()
    {
        return $this->belongsTo(Role::class, 'target_role_id');
    }

    public function targetDept()
    {
        return $this->belongsTo(Department::class, 'target_dept_id');
    }
}
