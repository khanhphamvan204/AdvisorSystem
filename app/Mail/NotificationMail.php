<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'Thông báo từ hệ thống',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'type' => $this->data['type'],
                'studentName' => $this->data['studentName'],
                'title' => $this->data['title'] ?? '',

                // Notification data
                'notificationTitle' => $this->data['notificationTitle'] ?? '',
                'notificationContent' => $this->data['notificationContent'] ?? '',
                'notificationLink' => $this->data['notificationLink'] ?? null,

                // Activity data
                'activityTitle' => $this->data['activityTitle'] ?? '',
                'activityDescription' => $this->data['activityDescription'] ?? '',
                'activityLocation' => $this->data['activityLocation'] ?? null,
                'activityTime' => $this->data['activityTime'] ?? null,
                'activityPoints' => $this->data['activityPoints'] ?? null,
                'activityLink' => $this->data['activityLink'] ?? null,

                // Warning data
                'warningTitle' => $this->data['warningTitle'] ?? '',
                'warningContent' => $this->data['warningContent'] ?? '',
                'warningAdvice' => $this->data['warningAdvice'] ?? null,

                // Meeting data
                'meetingTitle' => $this->data['meetingTitle'] ?? '',
                'meetingSummary' => $this->data['meetingSummary'] ?? '',
                'meetingLocation' => $this->data['meetingLocation'] ?? '',
                'meetingTime' => $this->data['meetingTime'] ?? '',
                'meetingLink' => $this->data['meetingLink'] ?? null,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}