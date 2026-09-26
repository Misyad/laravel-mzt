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

    public ?string $applicationNumber;

    public function __construct(string $code, string $purpose, ?string $applicationNumber = null)
    {
        $this->code = $code;
        $this->purpose = $purpose;
        $this->applicationNumber = $applicationNumber;
    }

    public function build()
    {
        return $this->subject($this->purpose)
            ->html($this->htmlContent());
    }

    public function htmlContent(): string
    {
        $applicationNumber = $this->applicationNumber === null
            ? ''
            : '<p>Nomor pendaftaran: <strong>'.e($this->applicationNumber).'</strong></p>';

        return $applicationNumber.'<p>Kode verifikasi Anda:</p><p><strong>'.e($this->code).'</strong></p><p>Kode ini akan segera kedaluwarsa.</p>';
    }
}
