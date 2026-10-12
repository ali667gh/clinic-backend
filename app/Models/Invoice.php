<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'amount',
        'discount',
        'final_amount',
        'status',
        'payment_method',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'discount'     => 'integer',
        'final_amount' => 'integer',
        'paid_at'      => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
