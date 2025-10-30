<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'Classes';
    protected $primaryKey = 'class_id';
    protected $fillable = ['class_name', 'advisor_id', 'faculty_id', 'description'];

    public function advisor()
    {
        return $this->belongsTo(Advisor::class, 'advisor_id', 'user_id');
    }
    public function faculty()
    {
        return $this->belongsTo(Unit::class, 'faculty_id', 'unit_id');
    }
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'notification_class');
    }
    public function meetings()
    {
        return $this->hasMany(Meeting::class, 'class_id');
    }
}