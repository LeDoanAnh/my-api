<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $table = 'assets';
    protected $fillable = ['asset_name', 'asset_code', 'unit', 'type', 'department_id', 'status'];

    public function department() {
        return $this->belongsTo(Department::class, 'department_id');
    }
   public function currentRequest()
{
    // Đổi Request::class thành AssetRequest::class
    return $this->hasOne(AssetRequest::class, 'asset_id')->whereNull('actual_return_date')->latest();
}

    // 3. Quan hệ với Lịch sử hoạt động
    public function history()
    {
        // Nếu không có bảng history riêng, lịch sử chính là tất cả các request cũ
        return $this->hasMany(AssetRequest::class, 'asset_id')->orderBy('created_at', 'desc');
    }

    public function getStatusLabelAttribute()
    {
        return $this->status === 'available' ? 'Sẵn sàng' : 'Đang cho mượn';
    }

}
