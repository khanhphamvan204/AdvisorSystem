<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationAttachment extends Model
{
    protected $primaryKey = 'attachment_id';
    public $timestamps = false;
    protected $fillable = ['notification_id', 'file_path', 'file_name'];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}