<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Department extends Model
{
    protected $table = 'departments';
    protected $fillable = [
        'dept_name',
        'location_desc',
        'parent_dept_id',
        'status',
    ];
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_dept_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_dept_id');
    }
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'department_id', 'id');
    }

    // Mối quan hệ 1-N với Locations
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'department_id', 'id');
    }
    public function users() {
        return $this->hasMany(User::class, 'department_id');
    }
    public function submissionContents()
    {
        return $this->hasMany(SubmissionStepContent::class, 'target_dept_id');
    }
}
