<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model
{
    protected $primaryKey = 'request_id';
    protected $fillable = ['registration_id', 'reason', 'status'];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(ActivityRegistration::class, 'registration_id');
    }
}