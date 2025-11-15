<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'Messages';
    public $timestamps = false;
    protected $primaryKey = 'message_id';
    protected $keyType = 'int';

    protected $fillable = [
        'student_id',
        'advisor_id',
        'sender_type',
        'content',
        'attachment_path',
        'is_read',
        'sent_at'
    ];

    protected $casts = [
        'sender_type' => 'string',
        'sent_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->sent_at)) {
                $message->sent_at = now();
            }
        });
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