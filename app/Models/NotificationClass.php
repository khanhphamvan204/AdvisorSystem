<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NotificationClass extends Pivot
{
    protected $table = 'notification_class';
    public $incrementing = false;
    protected $primaryKey = ['notification_id', 'class_id'];

    protected $fillable = ['notification_id', 'class_id'];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}