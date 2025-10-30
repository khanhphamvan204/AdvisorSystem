<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $primaryKey = 'unit_id';
    protected $fillable = ['unit_name', 'type', 'description'];
    protected $casts = ['type' => 'string'];

    public function classes()
    {
        return $this->hasMany(ClassModel::class, 'faculty_id');
    }
    public function advisors()
    {
        return $this->hasMany(Advisor::class, 'unit_id');
    }
}