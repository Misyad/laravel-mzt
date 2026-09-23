<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    public string $purpose;

    public function __construct(string $code, string $purpose)
    {
        $this->code = $code;
        $this->purpose = $purpose;
    }

    public function build()
    {
        return $this->subject($this->purpose)
            ->html('<p>Kode verifikasi Anda:</p><p><strong>'.e($this->code).'</strong></p><p>Kode ini akan segera kedaluwarsa.</p>');
    }
}
