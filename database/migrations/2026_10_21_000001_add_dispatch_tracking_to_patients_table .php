<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // زمان آخرین ویرایش مؤثر (نام، شماره پرونده، کد ملی یا موبایل)
            if (!Schema::hasColumn('patients', 'edited_at')) {
                $table->timestamp('edited_at')->nullable();
            }

            // زمان آخرین ارسال دوباره‌ی لینک جوابدهی برای این بیمار
            if (!Schema::hasColumn('patients', 'link_resent_at')) {
                $table->timestamp('link_resent_at')->nullable();
            }

            // کد ملی/شماره پرونده‌های قبلی بیمار (JSON) تا پرونده‌های بایگانی قدیمی هم پیدا شوند
            if (!Schema::hasColumn('patients', 'legacy_identifiers')) {
                $table->text('legacy_identifiers')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['edited_at', 'link_resent_at', 'legacy_identifiers'] as $column) {
                if (Schema::hasColumn('patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
