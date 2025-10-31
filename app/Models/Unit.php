<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'Units';
    public $timestamps = false;
    protected $primaryKey = 'unit_id';

    protected $fillable = ['unit_name', 'type', 'description'];

    protected $casts = [
        'type' => 'string', // ENUM('faculty', 'department')
    ];

    public function advisors(): HasMany
    {
        return $this->hasMany(Advisor::class, 'unit_id', 'unit_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'faculty_id', 'unit_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'organizer_unit_id', 'unit_id');
    }
}