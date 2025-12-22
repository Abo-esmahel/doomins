<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class WelcomeNewUser extends Mailable
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
            subject: 'PAC Team - مرحباً بك',
        );
    }

    public function content(): Content
    {
        return new Content(
    view: 'emails.WelcomeNewUser',
    with: [
        'user' => $this->user,
    ],
);

    }

    public function attachments(): array
    {
        return [];
    }
}
