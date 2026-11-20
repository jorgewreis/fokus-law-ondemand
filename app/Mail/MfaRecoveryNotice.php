<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MfaRecoveryNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Aviso de redefinição do autenticador Fokus Law');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.mfa-recovery-notice');
    }
}
