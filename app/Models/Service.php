<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['name', 'duration','price', 'specialist_id'];

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_services');
    }

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }
}
