<?php

namespace App\Http\Requests\Subscription;

use App\Models\WhatsappSubscriber;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normalisation du numéro avant validation (chiffres et « + » uniquement). */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => WhatsappSubscriber::normalizePhone($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'phone'   => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'lists'   => 'sometimes|array|min:1',
            'lists.*' => 'string|max:50',
            'consent' => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'      => 'Le numéro de téléphone est invalide.',
            'consent.accepted' => 'Votre consentement est requis pour vous abonner.',
        ];
    }
}
