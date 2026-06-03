<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SpecialistAvailability extends Model
{
    protected $table = 'specialist_availabilities';
    protected $fillable = [
        'specialist_id',
        'date',
        'start_time',
        'end_time'
    ];
    public function specialist()
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }
}
