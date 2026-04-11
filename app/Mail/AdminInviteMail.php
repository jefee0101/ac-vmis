<?php

namespace App\Mail;

use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminInvite $invite,
        public User $sender,
        public string $acceptUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to join AC-VMIS as an Administrator',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-invite',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
