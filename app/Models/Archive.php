<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasHistory;

class Archive extends Model
{
    use HasFactory;
    use HasHistory;

    protected $table = 'archives';

    protected $fillable = [
        'patient_name',
        'national_code',
        'file_number',
        'mobile',
        'issued_by',
        'issued_by_name',
        'issued_at',
        'form_data',
        'attachments',
        'tracking_token',
        'history',
    ];

    protected $casts = [
        'history'     => 'array',
        'form_data'   => 'array',
        'attachments' => 'array',
        'issued_at'   => 'datetime',
    ];

    protected $appends = [
        'audit_logs',
    ];

    public function getAuditLogsAttribute()
    {
        return $this->history ?? [];
    }

    /**
     * متد ثبت لاگ سازگار با فرانت‌اند
     */
    public function addHistoryLog($action, $details = '', $user = null, $createdAt = null)
    {
        $user = $user ?? auth()->user();
        $userName = is_string($user) ? $user : ($user ? ($user->name ?? $user->username ?? 'کاربر سامانه') : 'سیستم');
        $userRole = (is_object($user)) ? ($user->role ?? $user->role_name ?? 'کاربر') : 'کاربر';

        $logs = is_array($this->history) ? $this->history : json_decode($this->history ?? '[]', true);

        $logs[] = [
            'id'                 => count($logs) + 1,
            'user'               => [
                'name' => $userName,
                'role' => $userRole,
            ],
            'user_name'          => $userName,
            'user_role'          => $userRole,
            'action'             => $action,
            'action_description' => $details ?: $action,
            'created_at'         => $createdAt ? \Carbon\Carbon::parse($createdAt)->toIso8601String() : now()->toIso8601String(),
        ];

        $this->history = $logs;
        $this->save();
    }
}
