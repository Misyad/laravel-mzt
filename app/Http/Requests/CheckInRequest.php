<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for POST /api/checkin (PRD §17.8).
 *
 * The event is derived server-side from the ticket (ticket → order → id_event),
 * so id_event is deliberately not part of the payload.
 */
class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_uuid' => ['required', 'uuid'],
            'id_tanggal' => ['required', 'integer'],
            'gate' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_uuid.required' => 'ticket_uuid wajib diisi',
            'ticket_uuid.uuid' => 'ticket_uuid harus berupa UUID yang valid',
            'id_tanggal.required' => 'id_tanggal wajib diisi',
            'id_tanggal.integer' => 'id_tanggal harus berupa angka',
            'gate.max' => 'gate terlalu panjang',
        ];
    }
}
