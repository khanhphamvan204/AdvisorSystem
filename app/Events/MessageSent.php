<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $senderInfo;

    public function __construct(Message $message, array $senderInfo)
    {
        $this->message = $message;
        $this->senderInfo = $senderInfo;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('chat.student.' . $this->message->student_id),
            new PrivateChannel('chat.advisor.' . $this->message->advisor_id),
        ];
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'sender' => $this->senderInfo
        ];
    }
}