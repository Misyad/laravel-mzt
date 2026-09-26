<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemberApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $memberId;

    public string $applicationNumber;

    public string $token;

    public string $email;

    public function __construct(string $memberId, string $applicationNumber, string $token, string $email)
    {
        $this->memberId = $memberId;
        $this->applicationNumber = $applicationNumber;
        $this->token = $token;
        $this->email = $email;
    }

    public function build()
    {
        return $this->subject('Pendaftaran anggota MZT disetujui')
            ->html($this->htmlContent());
    }

    public function htmlContent(): string
    {
        return '<p>Pendaftaran anggota MZT Anda telah disetujui.</p><p>ID anggota: <strong>'.e($this->memberId).'</strong></p><p>Nomor pendaftaran: <strong>'.e($this->applicationNumber).'</strong></p><p><a href="'.e($this->claimUrl()).'">Buat password akun</a></p><p>Tautan ini hanya dapat digunakan satu kali dan akan segera kedaluwarsa.</p>';
    }

    public function claimUrl(): string
    {
        return rtrim((string) config('member_onboarding.frontend_url'), '/')
            .'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $this->email]);
    }
}
