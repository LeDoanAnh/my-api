<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class AssetRequest extends Model
{
    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
    public function borrower()
    {

        return $this->belongsTo(User::class, 'borrower_id');
    }
    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }
    protected $casts = [
        'expected_borrow_date' => 'datetime',
        'borrow_date'          => 'datetime',
        'expected_return_date' => 'datetime',
        'actual_return_date'   => 'datetime',
    ];

}
