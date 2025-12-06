<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $readerId;
    public $readerRole;

    public function __construct(Message $message, $readerId, $readerRole)
    {
        $this->message = $message;
        $this->readerId = $readerId;
        $this->readerRole = $readerRole;
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
        return 'message.read';
    }
}