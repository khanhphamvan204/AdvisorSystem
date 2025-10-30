<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'Notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = false;
    protected $fillable = ['advisor_id', 'title', 'summary', 'link', 'type'];

    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'Notification_Class', 'notification_id', 'class_id');
    }

    public function attachments()
    {
        return $this->hasMany(NotificationAttachment::class, 'notification_id');
    }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    public function responses()
    {
        return $this->hasMany(NotificationResponse::class, 'notification_id');
    }
}