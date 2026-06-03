<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'specialist_id',
        'start_time',
        'end_time',
        'status',
        'delay_minutes',
        'confirmation_email_sent_at',
        'cancellation_reason',
    ];
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function specialist()
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }


    public function services()
    {
        return $this->belongsToMany(Service::class, 'appointment_services');
    }
}
