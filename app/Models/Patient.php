<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'file_number',
        'national_code',
        'mobile',
        'address',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
