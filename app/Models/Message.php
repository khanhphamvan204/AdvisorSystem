<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'Messages';
    public $timestamps = false;
    protected $primaryKey = 'message_id';
    protected $keyType = 'bigIncrements';

    protected $fillable = [
        'student_id',
        'advisor_id',
        'sender_type',
        'content',
        'attachment_path',
        'is_read'
    ];

    protected $casts = [
        'sender_type' => 'string',
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'advisor_id');
    }
}