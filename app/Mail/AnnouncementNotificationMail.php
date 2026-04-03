<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AnnouncementNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $notificationTitle;
    public string $notificationMessage;
    public string $notificationTypeLabel;
    public string $actionUrl;

    public function __construct(
        User $user,
        string $notificationTitle,
        string $notificationMessage,
        string $notificationTypeLabel,
        string $actionUrl,
    ) {
        $this->user = $user;
        $this->notificationTitle = $notificationTitle;
        $this->notificationMessage = $notificationMessage;
        $this->notificationTypeLabel = $notificationTypeLabel;
        $this->actionUrl = $actionUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AC-VMIS Notification: ' . Str::limit($this->notificationTitle, 70, ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.announcement-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
