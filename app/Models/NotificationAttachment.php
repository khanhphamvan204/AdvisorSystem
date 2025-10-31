<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttachment extends Model
{
    protected $table = 'Notification_Attachments';
    protected $primaryKey = 'attachment_id';
    public $timestamps = false;

    protected $fillable = ['notification_id', 'file_path', 'file_name'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }
}