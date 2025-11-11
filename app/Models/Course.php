<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Thêm

class Course extends Model
{
    protected $table = 'Courses';
    public $timestamps = false;
    protected $primaryKey = 'course_id';

    protected $fillable = ['course_code', 'course_name', 'credits', 'unit_id'];

    public function grades(): HasMany
    {
        return $this->hasMany(CourseGrade::class, 'course_id', 'course_id');
    }

    // Thêm quan hệ: Môn học thuộc về 1 Đơn vị (Khoa/Phòng)
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }
}