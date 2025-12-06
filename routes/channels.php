<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Student;
use App\Models\Advisor;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel cho chat của student
Broadcast::channel('chat.student.{studentId}', function ($user, $studentId) {
    if (!$user) {
        return false;
    }

    // Lấy role và id từ JWT claims
    $role = $user->role ?? null;
    $userId = $user->id ?? null;

    // Kiểm tra user có phải là student này hoặc là advisor của student này
    if ($role === 'student' && $userId == $studentId) {
        return ['id' => $userId, 'role' => $role];
    }

    if ($role === 'advisor') {
        $student = Student::with('class')->find($studentId);
        if ($student && $student->class && $student->class->advisor_id == $userId) {
            return ['id' => $userId, 'role' => $role];
        }
    }

    return false;
});

// Channel cho chat của advisor
Broadcast::channel('chat.advisor.{advisorId}', function ($user, $advisorId) {
    if (!$user) {
        return false;
    }

    // Lấy role và id từ JWT claims
    $role = $user->role ?? null;
    $userId = $user->id ?? null;

    // Kiểm tra user có phải là advisor này hoặc là student của advisor này
    if ($role === 'advisor' && $userId == $advisorId) {
        return ['id' => $userId, 'role' => $role];
    }

    if ($role === 'student') {
        $student = Student::with('class')->find($userId);
        if ($student && $student->class && $student->class->advisor_id == $advisorId) {
            return ['id' => $userId, 'role' => $role];
        }
    }

    return false;
});
