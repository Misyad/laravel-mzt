<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared payload for ticket lifecycle actions (reissue / revoke).
 * Only an optional administrative note is expected.
 */
class TicketActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'catatan terlalu panjang',
        ];
    }
}