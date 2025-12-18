<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\ActivityClass;
use Carbon\Carbon;

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

    // Uncomment dòng dưới nếu muốn tự động thêm computed_status vào JSON response
    // protected $appends = ['computed_status'];

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
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'Activity_Class', 'activity_id', 'class_id')
            ->using(ActivityClass::class);
    }
    public function registrations(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            ActivityRegistration::class,
            ActivityRole::class,
            'activity_id',
            'activity_role_id',
            'activity_id',
            'activity_role_id'
        );
    }

    /**
     * Accessor: Tính toán trạng thái real-time dựa trên thời gian
     * Sử dụng: $activity->computed_status
     * 
     * @return string 'upcoming'|'ongoing'|'completed'|'cancelled'
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if (!$this->start_time || !$this->end_time) {
            return $this->status ?? 'upcoming';
        }

        $now = Carbon::now();

        if ($now->lt($this->start_time)) {
            return 'upcoming';
        } elseif ($now->gte($this->start_time) && $now->lt($this->end_time)) {
            return 'ongoing';
        }

        return 'completed';
    }

    /**
     * Cập nhật trạng thái vào database dựa trên thời gian
     * Được sử dụng bởi Command: UpdateActivityStatus
     * 
     * @return bool true nếu có cập nhật, false nếu không thay đổi
     */
    public function updateStatusBasedOnTime(): bool
    {
        $computedStatus = $this->computed_status;

        if ($this->status !== $computedStatus) {
            $this->status = $computedStatus;
            return $this->save();
        }

        return false;
    }
}