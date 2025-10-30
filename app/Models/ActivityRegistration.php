<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityRegistration extends Model
{
    protected $primaryKey = 'registration_id';
    protected $fillable = ['activity_role_id', 'student_id', 'status'];

    public function role()
    {
        return $this->belongsTo(ActivityRole::class, 'activity_role_id');
    }
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'user_id');
    }
    public function cancellationRequest()
    {
        return $this->hasOne(CancellationRequest::class, 'registration_id');
    }
}