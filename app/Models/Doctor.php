<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Models\Concerns\HasAuditLogs;

class Doctor extends Model
{
    use HasFactory, HasAuditLogs;

    protected $fillable = [
        'name',
        'specialty',
        'medical_council_code',
        'medical_code',
        'mobile',
        'stamp_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // اضافه کردن stamp_url به خروجی خودکار JSON
    protected $appends = ['stamp_url'];

    /**
     * اکسسور برای تولید URL کامل تصویر مهر
     */
    public function getStampUrlAttribute()
    {
        if (!$this->stamp_path) {
            return null;
        }

        // اگر از قبل URL کامل بود
        if (str_starts_with($this->stamp_path, 'http://') || str_starts_with($this->stamp_path, 'https://')) {
            return $this->stamp_path;
        }

        // تمیزسازی مسیر نسبی و تولید لینک استوریج
        $cleanPath = ltrim(str_replace('/storage/', '', $this->stamp_path), '/');
        return asset('storage/' . $cleanPath);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
