<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasHistory
{
    /**
     * ثبت لاگ در تاریخچه رکورد
     *
     * @param string $action عنوان عملیات (مثلا: افزودن خدمت، صدور فاکتور، ویرایش)
     * @param string $details توضیحات تکمیلی
     * @param array $extraData داده‌های اختیاری دیگر (مثلا قیمت یا کد رهگیری)
     * @return bool
     */
    public function recordHistory(string $action, string $details = '', array $extraData = []): bool
    {
        // دریافت تاریخچه قبلی یا مقداردهی اولیه به صورت آرایه خالی
        $history = is_array($this->history) ? $this->history : [];

        // تشخیص کاربر جاری
        $user = Auth::user();
        $userName = $user ? ($user->name ?? $user->full_name ?? $user->username ?? 'کاربر ناشناس') : 'سیستم خودکار';
        $userId = $user ? $user->id : null;

        // ساخت لاگ جدید
        $newLog = [
            'user_id'    => $userId,
            'user_name'  => $userName,
            'action'     => $action,
            'details'    => $details,
            'created_at' => now()->format('Y/m/d H:i:s'),
        ];

        // در صورت وجود اطلاعات اضافی، اضافه شود
        if (!empty($extraData)) {
            $newLog['extra'] = $extraData;
        }

        // اضافه کردن لاگ جدید به ابتدای لیست (جدیدترین‌ها اول نمایش داده شوند)
        array_unshift($history, $newLog);

        // بروزرسانی فیلد تاریخچه
        return $this->update(['history' => $history]);
    }
}
