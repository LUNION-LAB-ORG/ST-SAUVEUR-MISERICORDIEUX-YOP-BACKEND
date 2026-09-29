<?php

namespace App\Http\Requests\MassRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /** Moyens de paiement connus (seuls wave et secretariat sont actifs pour l'instant). */
    public const PAYMENT_METHODS = ['wave', 'orange', 'mtn', 'moov', 'card', 'secretariat'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Étape 1 : l'intention
            'intention_type'  => 'required|string|max:100',
            'for_whom'        => 'required|string|max:150',
            'intention'       => 'nullable|string|max:250',
            'is_confidential' => 'sometimes|boolean',
            // Étape 2 : la date
            'formula'         => 'sometimes|string|in:single,triduum,novena',
            'date'            => 'required|date_format:Y-m-d',
            'time_slot_id'    => 'required|integer|exists:time_slots,id',
            // Étape 3 : les coordonnées
            'fullname'        => 'required|string|max:255',
            'phone'           => 'required|string|max:30',
            'email'           => 'nullable|email|max:255',
            'will_attend'     => 'sometimes|boolean',
            'reminder'        => 'sometimes|boolean',
            // Étape 4 : l'offrande
            'offering'        => 'sometimes|string|in:indicative,free',
            'amount'          => 'nullable|integer|min:0',
            'payment_method'  => 'required|string|in:' . implode(',', self::PAYMENT_METHODS),
        ];
    }
}
