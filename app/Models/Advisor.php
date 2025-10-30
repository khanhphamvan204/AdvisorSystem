<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advisor extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'unit_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
    public function classes()
    {
        return $this->hasMany(ClassModel::class, 'advisor_id', 'user_id');
    }
    public function activities()
    {
        return $this->hasMany(Activity::class, 'advisor_id', 'user_id');
    }
    public function warnings()
    {
        return $this->hasMany(AcademicWarning::class, 'advisor_id', 'user_id');
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'advisor_id', 'user_id');
    }
    public function meetings()
    {
        return $this->hasMany(Meeting::class, 'advisor_id', 'user_id');
    }
    public function monitoringNotes()
    {
        return $this->hasMany(StudentMonitoringNote::class, 'advisor_id', 'user_id');
    }
}