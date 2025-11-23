<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject; // Bắt buộc

class Advisor extends Model implements JWTSubject
{
    protected $table = 'Advisors';
    public $timestamps = false;
    protected $primaryKey = 'advisor_id';
    protected $fillable = [
        'user_code',
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'avatar_url',
        'unit_id',
        'role'
    ];

    // Thêm $hidden để bảo mật
    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    // (Các hàm quan hệ của bạn giữ nguyên)
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'advisor_id', 'advisor_id');
    }

    public function academicWarnings(): HasMany
    {
        return $this->hasMany(AcademicWarning::class, 'advisor_id', 'advisor_id');
    }

    public function pointFeedbackResponses(): HasMany
    {
        return $this->hasMany(PointFeedback::class, 'advisor_id', 'advisor_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'advisor_id', 'advisor_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'advisor_id', 'advisor_id');
    }

    public function notificationResponses(): HasMany
    {
        return $this->hasMany(NotificationResponse::class, 'advisor_id', 'advisor_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'advisor_id', 'advisor_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'advisor_id', 'advisor_id')
            ->where('sender_type', 'advisor');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'advisor_id', 'advisor_id')
            ->where('sender_type', 'student');
    }

    public function monitoringNotes(): HasMany
    {
        return $this->hasMany(StudentMonitoringNote::class, 'advisor_id', 'advisor_id');
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // Load unit nếu chưa có
        if (!$this->relationLoaded('unit')) {
            $this->load('unit');
        }

        $claims = [
            'id' => $this->advisor_id,
            'role' => $this->role,
            'name' => $this->full_name
        ];

        // Thêm tên đơn vị nếu có
        if ($this->unit) {
            $claims['unit_name'] = $this->unit->unit_name;
        }

        return $claims;
    }
}