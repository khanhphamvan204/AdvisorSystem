<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationClass extends Pivot
{
    /**
     * Tên bảng trung gian
     */
    protected $table = 'Notification_Class';


    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'notification_id',
        'class_id'
    ];

    public $timestamps = false;

    /**
     * Quan hệ: Thông báo
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }

    /**
     * Quan hệ: Lớp học
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id', 'class_id');
    }
}