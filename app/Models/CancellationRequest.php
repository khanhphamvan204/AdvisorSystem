<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRequest extends Model
{
    protected $table = 'Cancellation_Requests';
    public $timestamps = false;
    protected $primaryKey = 'request_id';

    protected $fillable = ['registration_id', 'reason', 'status', 'requested_at'];

    protected $casts = [
        'status' => 'string',
        'requested_at' => 'datetime',
    ];

    /**
     * Serialize datetime theo timezone hiện tại thay vì UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ActivityRegistration::class, 'registration_id', 'registration_id');
    }
}