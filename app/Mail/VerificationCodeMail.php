<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $otp_code; // أضفنا المتغير

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $otp_code)
    {
        $this->user = $user;
        $this->otp_code = $otp_code; // تمرير الكود الأصلي
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد حسابك - PAC Team',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.VerificationCodeMail',
            with: [
                'user' => $this->user,
                'otp_code' => $this->otp_code,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
