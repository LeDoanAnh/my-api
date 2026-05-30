<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionLocation extends Model
{

  protected $table = 'submission_locations';
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];


    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    public function location() {
    return $this->belongsTo(Location::class, 'location_id');
}
}
