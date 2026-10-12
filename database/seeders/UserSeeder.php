<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ایجاد کاربر مدیر کل با شماره موبایل
        User::updateOrCreate(
            ['mobile' => '09123456789'], // کلید یکتا برای جستجو
            [
                'name'      => 'مدیر کل سامانه',
                'role'      => 'admin', // ✅ مقدار معتبر براساس enum مایگریشن
                'password'  => '12345678', // به دلیل کست مدل، خودکار هش می‌شود
                'is_active' => true,
            ]
        );
    }
}
