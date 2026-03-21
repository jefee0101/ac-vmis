<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountPendingApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your AC-VMIS Registration Is Pending Approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-pending-approval',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

