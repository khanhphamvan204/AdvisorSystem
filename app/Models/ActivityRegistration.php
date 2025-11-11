<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActivityRegistration extends Model
{
    protected $table = 'Activity_Registrations';
    public $timestamps = false;
    protected $primaryKey = 'registration_id';

    protected $fillable = ['activity_role_id', 'student_id', 'status', 'registration_time'];

    protected $casts = [
        'registration_time' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(ActivityRole::class, 'activity_role_id', 'activity_role_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function cancellationRequest(): HasOne
    {
        return $this->hasOne(CancellationRequest::class, 'registration_id', 'registration_id');
    }
}