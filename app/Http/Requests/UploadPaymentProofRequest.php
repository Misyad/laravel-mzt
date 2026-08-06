<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload payment proof (PRD §21.6, §23.7).
 * Multipart: payment_proof (JPG/JPEG/PNG/PDF, max 5 MB) + optional meta fields.
 */
class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.required' => 'bukti pembayaran wajib diunggah',
            'payment_proof.file' => 'bukti pembayaran harus berupa file',
            'payment_proof.mimes' => 'format bukti tidak diizinkan (JPG/JPEG/PNG/PDF)',
            'payment_proof.max' => 'ukuran bukti maksimal 5 MB',
            'bank_name.max' => 'nama bank terlalu panjang',
            'account_name.max' => 'nama rekening terlalu panjang',
            'notes.max' => 'catatan terlalu panjang',
        ];
    }
}