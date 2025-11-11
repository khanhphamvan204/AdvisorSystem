<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Thêm

class ActivityClass extends Pivot
{
    protected $table = 'Activity_Class';

    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'class_id',
    ];


    /**
     * Quan hệ: Hoạt động
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id', 'activity_id');
    }

    /**
     * Quan hệ: Lớp học
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id', 'class_id');
    }
}