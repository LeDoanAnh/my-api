<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations';
    protected $fillable = ['location_name', 'capacity', 'address', 'department_id', 'status'];

    public function department() {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function submissionLocations() {
    return $this->hasMany(SubmissionLocation::class, 'location_id');
}
}
