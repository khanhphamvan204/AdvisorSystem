<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityRole extends Model
{
    protected $primaryKey = 'activity_role_id';
    protected $fillable = [
        'activity_id',
        'role_name',
        'description',
        'requirements',
        'points_awarded',
        'point_type',
        'max_slots'
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
    public function registrations()
    {
        return $this->hasMany(ActivityRegistration::class, 'activity_role_id');
    }
}