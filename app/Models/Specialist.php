<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialist extends Model
{
    protected $fillable = [
        'user_id',
        'specialization'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function availability()
    {
        return $this->hasMany(SpecialistAvailability::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
