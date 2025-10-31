<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $table = 'Activities';
    protected $primaryKey = 'activity_id';
    public $timestamps = false;

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

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }

    public function organizerUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'organizer_unit_id', 'unit_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ActivityRole::class, 'activity_id', 'activity_id');
    }
}