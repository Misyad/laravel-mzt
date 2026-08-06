<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Verify (approve/reject) a payment (PRD §21.6, §17.14.3).
 * Only PAID and REJECTED are valid final statuses.
 */
class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([PaymentStatus::PAID->value, PaymentStatus::REJECTED->value])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'status wajib diisi',
            'status.in' => 'status harus paid atau rejected',
            'note.max' => 'catatan terlalu panjang',
        ];
    }
}