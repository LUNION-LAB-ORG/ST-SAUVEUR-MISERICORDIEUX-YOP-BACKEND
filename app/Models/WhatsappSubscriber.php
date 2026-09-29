<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Abonné aux diffusions WhatsApp.
 */
class WhatsappSubscriber extends Model
{
    public const DEFAULT_LISTS = ['parole', 'annonces'];

    protected $guarded = [];

    protected $casts = [
        'lists'           => 'array',
        'consented_at'    => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /** Normalise un numéro : ne conserve que les chiffres et le « + ». */
    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $phone);
    }
}
