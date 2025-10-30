<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $primaryKey = 'activity_id';
    protected $fillable = [
        'advisor_id',
        'organizer_unit_id',
        'title',
        'general_description',
        'location',
        'start_time',
        'end_time',
        'status'
    ];

    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }
    public function organizer()
    {
        return $this->belongsTo(Unit::class, 'organizer_unit_id', 'unit_id');
    }
    public function roles()
    {
        return $this->hasMany(ActivityRole::class, 'activity_id');
    }
}