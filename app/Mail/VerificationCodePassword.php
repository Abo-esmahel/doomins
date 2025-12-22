<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class VerificationCodePassword extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $token;

    public function __construct(User $user, string $token)
    {
        $this->user  = $user;
        $this->token = $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد رغبتك في إعادة تعيين كلمة المرور'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.VerificationCodePassword',
            with: [
                'user'  => $this->user,
                'token' => $this->token, // ← هنا نمرّر التوكن للصفحة
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
