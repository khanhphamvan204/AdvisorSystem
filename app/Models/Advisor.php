<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

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
        'unit_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    // Quan hệ: CVHT thuộc 1 đơn vị
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    // Quan hệ: CVHT quản lý nhiều lớp
    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT tạo nhiều cảnh cáo
    public function academicWarnings(): HasMany
    {
        return $this->hasMany(AcademicWarning::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT xử lý khiếu nại
    public function pointFeedbackResponses(): HasMany
    {
        return $this->hasMany(PointFeedback::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT tạo hoạt động
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT tạo thông báo
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT phản hồi thông báo
    public function notificationResponses(): HasMany
    {
        return $this->hasMany(NotificationResponse::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: CVHT tổ chức họp
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'advisor_id', 'advisor_id');
    }

    // Quan hệ: Tin nhắn
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

    // Quan hệ: CVHT ghi chú theo dõi
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
        return [
            'id' => $this->advisor_id,
            'role' => 'advisor',
            'name' => $this->full_name
        ];
    }
}