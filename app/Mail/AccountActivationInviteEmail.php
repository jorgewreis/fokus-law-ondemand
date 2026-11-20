<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountActivationInviteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $activationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Convite para ativar sua conta Fokus Law');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.account-activation');
    }
}
