<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $table = 'Notifications';
    public $timestamps = false;
    protected $primaryKey = 'notification_id';

    protected $fillable = ['advisor_id', 'title', 'summary', 'link', 'type'];

    protected $casts = [
        'created_at' => 'datetime',
        'type' => 'string',
    ];

    /**
     * Serialize datetime theo timezone hiện tại thay vì UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'Notification_Class', 'notification_id', 'class_id')->using(NotificationClass::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NotificationAttachment::class, 'notification_id', 'notification_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id', 'notification_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(NotificationResponse::class, 'notification_id', 'notification_id');
    }
}