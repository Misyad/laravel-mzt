<?php

namespace App\Http\Requests;

use App\Services\KtaLookupService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Lookup payload for POST /api/public/kta/check.
 *
 * Only the two documented modes are accepted. Unknown fields (email, no_hp,
 * alamat, ...) are rejected outright so the endpoint cannot be repurposed as
 * a PII probe. Date-of-birth sentinels are rejected as a validation failure
 * BEFORE any database query runs.
 */
class KtaCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:name_dob,member_id'],
            'name' => ['required_if:mode,name_dob', 'nullable', 'string', 'min:3', 'max:100'],
            'tanggal_lahir' => ['required_if:mode,name_dob', 'nullable', 'date_format:Y-m-d'],
            'id_anggota' => ['required_if:mode,member_id', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.required' => 'mode wajib diisi',
            'mode.in' => 'mode tidak dikenal',
            'name.required_if' => 'nama wajib diisi',
            'name.min' => 'nama terlalu pendek',
            'name.max' => 'nama terlalu panjang',
            'tanggal_lahir.required_if' => 'tanggal lahir wajib diisi',
            'tanggal_lahir.date_format' => 'format tanggal lahir harus YYYY-MM-DD',
            'id_anggota.required_if' => 'nomor anggota wajib diisi',
        ];
    }

    /**
     * Reject sentinel / out-of-range dates and unknown fields before the
     * controller sees the payload.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['mode', 'name', 'tanggal_lahir', 'id_anggota'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('request', 'field tidak dikenal');
            }

            if ($this->input('mode') === 'name_dob') {
                $lookup = app(KtaLookupService::class);
                if ($lookup->normalizeDob($this->input('tanggal_lahir')) === null) {
                    $validator->errors()->add('tanggal_lahir', 'tanggal lahir tidak valid');
                }
            }
        });
    }

    /**
     * Keep the public error envelope generic (no field-level hints that could
     * act as an oracle).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Permintaan tidak valid',
        ], 400));
    }
}
