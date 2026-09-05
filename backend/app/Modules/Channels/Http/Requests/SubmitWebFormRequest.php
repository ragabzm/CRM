<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Requests;

use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The six fields, and nothing else that reaches the pipeline.
 *
 * The set is fixed by the story and is not configurable: name, contact,
 * subject, category, message, attachment. Two more arrive with the request and
 * neither is a field a person fills in — `hp_company` is the honeypot and
 * `rendered_at` is when the page was drawn — so both are validated here and
 * dropped before anything downstream sees them.
 */
final class SubmitWebFormRequest extends FormRequest
{
    /** Anybody. That is the point of a public form. */
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
            'name' => ['required', 'string', 'max:200'],
            'contact' => ['required', 'string', 'max:320'],
            'subject' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'message' => ['required', 'string', 'max:20000'],

            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['string', 'size:26'],

            'session_token' => ['required_with:attachment_ids', 'nullable', 'string', 'size:26'],

            /*
             * Must arrive; its CONTENTS are not validated here.
             *
             * A 422 saying "this field must be empty" tells whoever wrote the
             * robot exactly which field to stop filling in. The controller
             * answers a filled honeypot with 202 and a normal-looking body
             * instead, and that only works if validation lets it through.
             */
            'hp_company' => ['present', 'nullable', 'string', 'max:200'],

            'rendered_at' => ['required', 'date'],
        ];
    }

    /**
     * The one rule the field list cannot express: a contact is an email or a
     * phone number, and neither alone is enough to describe the field.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $contact = trim((string) $this->input('contact', ''));

            if ($contact === '') {
                return;
            }

            if (str_contains($contact, '@')) {
                if (filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add('contact', 'Enter a valid email address.');
                }

                return;
            }

            /*
             * A phone, compared the way the rest of the system compares them.
             * Requiring E.164 here would refuse a number written the way most
             * people write their own, and the normaliser exists precisely so
             * that "020 7946 0958" and "+44 20 7946 0958" are one number.
             */
            if (mb_strlen(IdentifierNormaliser::normalise(ContactKind::Phone, $contact)) < 7) {
                $validator->errors()->add('contact', 'Enter an email address or a phone number.');
            }
        });
    }

}
