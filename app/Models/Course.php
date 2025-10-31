<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'Courses';
    public $timestamps = false;
    protected $primaryKey = 'course_id';

    protected $fillable = ['course_code', 'course_name', 'credits'];

    public function grades(): HasMany
    {
        return $this->hasMany(CourseGrade::class, 'course_id', 'course_id');
    }
}