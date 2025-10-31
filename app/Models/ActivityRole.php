<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityRole extends Model
{
    protected $table = 'Activity_Roles';
    public $timestamps = false;
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

    protected $casts = [
        'point_type' => 'string', // ctxh, ren_luyen
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id', 'activity_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class, 'activity_role_id', 'activity_role_id');
    }
}