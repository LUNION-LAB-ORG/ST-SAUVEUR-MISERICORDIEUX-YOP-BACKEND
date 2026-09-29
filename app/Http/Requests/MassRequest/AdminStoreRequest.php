<?php

namespace App\Http\Requests\MassRequest;

/**
 * Saisie d'une demande de messe au secrétariat : mêmes champs que la demande en ligne,
 * paiement « secretariat » (à régler) ou « cash » (encaissé), statut de paiement facultatif.
 */
class AdminStoreRequest extends StoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'payment_method' => 'required|string|in:secretariat,cash',
            'payment_status' => 'sometimes|nullable|string|in:pending,to_pay,succeeded,failed',
        ]);
    }
}
