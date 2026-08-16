<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode OTP 6-digit — dipakai dua konteks (dibedakan lewat $purpose):
 * verifikasi akun baru saat register, dan reset password lupa kata sandi.
 * Satu Mailable dipakai bareng supaya tidak duplikasi template.
 */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $code,
        public string $purpose, // 'verification' | 'password_reset'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === 'password_reset'
                ? 'Kode Reset Password — SMK Adaptif'
                : 'Kode Verifikasi Akun — SMK Adaptif',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-code',
            with: [
                'name'      => $this->name,
                'code'      => $this->code,
                'isReset'   => $this->purpose === 'password_reset',
            ],
        );
    }
}
