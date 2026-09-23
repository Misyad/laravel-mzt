<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetTokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;

    public string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function build()
    {
        $url = rtrim((string) config('member_onboarding.frontend_url'), '/')
            .'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $this->email]);

        return $this->subject('Reset password akun MZT')
            ->html('<p>Gunakan tautan berikut untuk mengatur ulang password akun MZT Anda:</p><p><a href="'.e($url).'">Atur ulang password</a></p><p>Tautan ini hanya dapat digunakan satu kali dan akan segera kedaluwarsa.</p>');
    }
}
