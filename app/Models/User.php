<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'department_id',
        'status',
        'fcm_token',
        'signature_path',
        'is_first_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'department_id' => 'integer',
            'is_first_login' => 'boolean',
        ];
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }
    public function department()
    {
    return $this->belongsTo(Department::class, 'department_id');
    }
    public function submissions() {
        return $this->hasMany(Submission::class, 'creator_id');
    }

    public function notifications() {
        return $this->hasMany(Notification::class, 'user_id');
    }
}
