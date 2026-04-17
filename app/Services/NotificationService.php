<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Service simple pour créer les notifications admin.
 * Appelé depuis les controllers lors d'événements métiers.
 */
class NotificationService
{
    public static function create(array $data): Notification
    {
        return Notification::create(array_merge([
            'is_read' => false,
        ], $data));
    }

    public static function forMesse(\App\Models\Mess $messe): void
    {
        self::create([
            'type'         => 'messe',
            'icon'         => 'flame',
            'title'        => 'Nouvelle demande de messe',
            'message'      => sprintf('%s a soumis une demande de messe (%s).', $messe->fullname, $messe->type ?? 'intention'),
            'related_type' => 'messe',
            'related_id'   => $messe->id,
            'link'         => '/dashboard/messes',
        ]);
    }

    public static function forListen(\App\Models\Listen $listen): void
    {
        self::create([
            'type'         => 'listen',
            'icon'         => 'volume2',
            'title'        => 'Nouvelle demande d\'écoute',
            'message'      => sprintf('%s souhaite une écoute (%s).', $listen->fullname, $listen->type ?? 'spirituelle'),
            'related_type' => 'listen',
            'related_id'   => $listen->id,
            'link'         => '/dashboard/ecoutes',
        ]);
    }

    public static function forDonation(\App\Models\Donation $donation): void
    {
        $isNature = ($donation->donation_type ?? 'monetaire') === 'nature';
        self::create([
            'type'         => 'donation',
            'icon'         => 'heart',
            'title'        => $isNature ? 'Don en nature reçu' : 'Don reçu',
            'message'      => $isNature
                ? sprintf('%s a offert : %s.', $donation->donator, $donation->description ?? 'Don en nature')
                : sprintf('%s a fait un don de %s F pour "%s".', $donation->donator, number_format((float) $donation->amount, 0, ',', ' '), $donation->project),
            'related_type' => 'donation',
            'related_id'   => $donation->id,
            'link'         => '/dashboard/dons',
        ]);
    }

    public static function forEventRegistration(\App\Models\ParticipantEvent $participant, ?\App\Models\Event $event = null): void
    {
        $eventTitle = $event ? $event->title : 'un événement';
        self::create([
            'type'         => 'event_register',
            'icon'         => 'calendar',
            'title'        => 'Nouvel inscrit à un événement',
            'message'      => sprintf('%s s\'est inscrit(e) à "%s".', $participant->fullname, $eventTitle),
            'related_type' => 'event',
            'related_id'   => $event?->id ?? $participant->event_id,
            'link'         => $event ? '/dashboard/evenements/' . $event->id : '/dashboard/evenements',
        ]);
    }

    public static function forOrganisation(\App\Models\Organisation $organisation): void
    {
        self::create([
            'type'         => 'organisation',
            'icon'         => 'calendar-plus',
            'title'        => 'Nouvelle demande d\'événement',
            'message'      => sprintf('%s propose "%s" (%s).', $organisation->email, $organisation->title ?? $organisation->eventType, $organisation->movement ?? '—'),
            'related_type' => 'organisation',
            'related_id'   => $organisation->id,
            'link'         => '/dashboard/organisations/' . $organisation->id,
        ]);
    }
}
