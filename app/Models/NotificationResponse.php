<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationResponse extends Model
{
    protected $table = 'Notification_Responses';
    public $timestamps = false;
    protected $primaryKey = 'response_id';

    protected $fillable = [
        'notification_id',
        'student_id',
        'content',
        'status',
        'advisor_response',
        'advisor_id',
        'response_at'
    ];

    protected $casts = [
        'status' => 'string',
        'response_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }
}