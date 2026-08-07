<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PUT /api/notifications/read.
 */
class NotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is enforced inside NotificationService.
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'max:60'],
        ];
    }
}