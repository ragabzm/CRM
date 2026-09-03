<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * What a customer has to give to open an account.
 *
 * Four fields, and no more. Every extra one is a reason somebody abandons a
 * form they only opened because something had already gone wrong.
 */
final class RegisterPortalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],

            'email' => [
                'required',
                'email:rfc',
                'max:320',
                // Case-insensitively unique: `Hana@x.test` and `hana@x.test`
                // are one person, and letting both register would split their
                // requests across two accounts neither of them can see whole.
                Rule::unique('portal_accounts', 'email'),
            ],

            'password' => [
                'required',
                'confirmed',
                /*
                 * Length and a breach check, not a character-class puzzle.
                 * Composition rules push people toward `Passw0rd!` — which is
                 * in every breach corpus — while length is what actually costs
                 * an attacker time.
                 */
                Password::min(10)->uncompromised(),
            ],

            /*
             * `nullable` as well as `sometimes`: an explicit null means "I have
             * no preference", which is a legitimate thing for a client to say
             * and different from sending nothing. Both fall back to English.
             */
            'preferred_locale' => ['sometimes', 'nullable', Rule::in(['en', 'ar'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account already exists for this address. Sign in instead, or reset your password.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            // Normalised once, here, so the uniqueness rule and the customer
            // lookup below both see the same string.
            $this->merge(['email' => strtolower(trim($email))]);
        }
    }
}
