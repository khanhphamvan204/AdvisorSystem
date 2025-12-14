<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $studentId;
    public $advisorId;
    public $userId;
    public $userRole;
    public $isTyping;

    public function __construct($studentId, $advisorId, $userId, $userRole, $isTyping)
    {
        $this->studentId = $studentId;
        $this->advisorId = $advisorId;
        $this->userId = $userId;
        $this->userRole = $userRole;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('chat.student.' . $this->studentId),
            new PrivateChannel('chat.advisor.' . $this->advisorId),
        ];
    }

    public function broadcastAs()
    {
        return 'user.typing';
    }

    public function broadcastWith()
    {
        return [
            'user_id' => $this->userId,
            'user_role' => $this->userRole,
            'sender_name' => $this->getSenderName(),
            'is_typing' => $this->isTyping,
        ];
    }

    private function getSenderName()
    {
        if ($this->userRole === 'student') {
            $student = \App\Models\Student::find($this->userId);
            return $student ? $student->full_name : 'Unknown Student';
        } elseif ($this->userRole === 'advisor') {
            $advisor = \App\Models\Advisor::find($this->userId);
            return $advisor ? $advisor->full_name : 'Unknown Advisor';
        }
        return 'Unknown User';
    }
}
