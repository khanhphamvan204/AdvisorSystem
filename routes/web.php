<?php

use Illuminate\Support\Facades\Route;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Student;
use App\Models\Advisor;

Route::get('/', function () {
    return response()->json([
        'message' => 'Advisor System API',
        'version' => '1.0.0'
    ]);
});

// Test route để broadcast message
Route::get('/test-broadcast', function () {
    // Lấy message mẫu (hoặc tạo mới)
    $message = Message::first();

    if (!$message) {
        // Tạo message test nếu chưa có
        $message = new Message([
            'message_id' => 999,
            'student_id' => 1,
            'advisor_id' => 1,
            'sender_type' => 'student',
            'content' => 'This is a test broadcast message',
            'is_read' => false,
            'sent_at' => now()
        ]);
    }

    $senderInfo = [
        'id' => $message->student_id,
        'name' => 'Test User',
        'avatar' => null,
        'type' => 'student'
    ];

    // Broadcast event
    broadcast(new MessageSent($message, $senderInfo))->toOthers();

    return response()->json([
        'success' => true,
        'message' => 'Event broadcasted successfully!',
        'data' => [
            'message' => $message,
            'sender' => $senderInfo,
            'channels' => [
                'chat.student.' . $message->student_id,
                'chat.advisor.' . $message->advisor_id
            ]
        ]
    ]);
});

// WebSocket Chat Test - New interface with dynamic JWT authentication
Route::get('/websocket-chat', function () {
    return view('websocket-chat');
});
