<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_at',
        'latitude',
        'longitude',
        'type',
        'image_path'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
