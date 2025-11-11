<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\ActivityClass;

class ClassModel extends Model
{
    protected $table = 'Classes';
    public $timestamps = false;
    protected $primaryKey = 'class_id';

    protected $fillable = ['class_name', 'advisor_id', 'faculty_id', 'description'];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'faculty_id', 'unit_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id', 'class_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'Notification_Class', 'class_id', 'notification_id')->using(NotificationClass::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'class_id', 'class_id');
    }
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'Activity_Class', 'class_id', 'activity_id')
            ->using(ActivityClass::class);
    }
}