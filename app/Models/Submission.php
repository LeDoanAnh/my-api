<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    /**
     * Tên bảng tương ứng trong database.
     * Dựa trên hình ảnh bạn cung cấp là 'submissions'.
     */
    protected $table = 'submissions';

    /**
     * Các thuộc tính có thể gán hàng loạt (Mass Assignable).
     * Điều này giúp bạn sử dụng được hàm Submission::create([...])
     */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'status',
        'priority',
        'department_id'
    ];

    /**
     * Ép kiểu dữ liệu cho các cột đặc biệt.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Thiết lập mối quan hệ: Một tờ trình thuộc về một người dùng.
     * Giúp bạn có thể gọi $submission->user để lấy thông tin người tạo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nếu bạn có bảng departments, hãy thêm quan hệ này để biết tờ trình gửi đến đâu.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
