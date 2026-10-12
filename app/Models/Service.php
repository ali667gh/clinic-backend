<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'price',
        'is_active',
        'requires_attachment',
        'has_signature',
        'signature_x',
        'signature_y',
        'signature_page',
        'header_path',
        'is_visit',
        'sort_order',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'requires_attachment' => 'boolean',
        'is_visit'            => 'boolean',
        'price'               => 'integer',
        'sort_order'          => 'integer',
        'has_signature'       => 'boolean',
        'signature_x'         => 'float',
        'signature_y'         => 'float',
        'signature_page'      => 'string',
    ];

    // مسیر داخلی فایل در پاسخ API نمایش داده نمی‌شود؛ به‌جایش has_header برمی‌گردد
    protected $hidden = ['header_path'];

    protected $appends = ['has_header'];

    // آیا این خدمت تصویر سربرگ دارد؟
    public function getHasHeaderAttribute(): bool
    {
        return !empty($this->attributes['header_path'] ?? null);
    }

    // رابطه با والد
    public function parent()
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    // رابطه با زیرمجموعه‌ها
    public function children()
    {
        return $this->hasMany(Service::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    // گرفتن فقط ریشه‌ها همراه زیرخدمات
    public function scopeRootsWithChildren($query)
    {
        return $query->whereNull('parent_id')->with('children');
    }
}
