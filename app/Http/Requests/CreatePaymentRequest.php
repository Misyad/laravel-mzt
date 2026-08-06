<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a payment on behalf of an order (staff / panitia, PRD §13).
 * Used for cash / sponsor / complimentary which become PAID immediately.
 */
class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'string', 'max:36'],
            'method' => ['required', 'string', \Illuminate\Validation\Rule::in(PaymentMethod::values())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_uuid.required' => 'order uuid wajib diisi',
            'method.required' => 'metode pembayaran wajib diisi',
            'method.in' => 'metode pembayaran tidak valid',
            'amount.required' => 'nominal wajib diisi',
            'amount.min' => 'nominal harus lebih dari 0',
            'reference_number.max' => 'nomor referensi terlalu panjang',
            'note.max' => 'catatan terlalu panjang',
        ];
    }
}