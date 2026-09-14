<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Verification payload for POST /api/public/kta/verify.
 *
 * `value` is intentionally polymorphic per method:
 *  - hp_last4        : scalar string, last 4 digits
 *  - no_hp_fallback  : { tahun_masuk, tempat_lahir } — two fields required
 *  - disambiguate    : scalar string for the requested field
 */
class KtaVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'method' => ['required', 'string', 'in:hp_last4,no_hp_fallback,disambiguate'],
            'value' => ['required'],
            'field' => ['required_if:method,disambiguate', 'nullable', 'string', 'in:tahun_masuk,tempat_lahir,niqobah'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'challenge token wajib diisi',
            'method.required' => 'metode verifikasi wajib diisi',
            'method.in' => 'metode verifikasi tidak dikenal',
            'value.required' => 'jawaban verifikasi wajib diisi',
            'field.required_if' => 'field disambiguasi wajib diisi',
            'field.in' => 'field disambiguasi tidak dikenal',
        ];
    }

    /**
     * Structural validation only — we deliberately do NOT reveal whether an
     * answer is right or wrong here (that is computed by the controller and
     * responds with a generic 403 + attempts_left).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['challenge_token', 'method', 'value', 'field'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('request', 'field tidak dikenal');
            }

            if ($this->input('method') === 'no_hp_fallback') {
                $value = $this->input('value');
                if (! is_array($value)
                    || trim((string) ($value['tahun_masuk'] ?? '')) === ''
                    || trim((string) ($value['tempat_lahir'] ?? '')) === '') {
                    $validator->errors()->add('value', 'jawaban verifikasi tidak lengkap');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Permintaan tidak valid',
        ], 400));
    }
}
