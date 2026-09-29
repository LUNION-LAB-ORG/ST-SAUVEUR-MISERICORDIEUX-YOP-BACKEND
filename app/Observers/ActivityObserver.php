<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\ChurchProject;
use App\Models\Donation;
use App\Models\Event;
use App\Models\HistoryMilestone;
use App\Models\Homily;
use App\Models\Listen;
use App\Models\Mess;
use App\Models\ParticipantEvent;
use App\Models\Priest;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Observateur unique du journal d'activité : traduit les événements Eloquent
 * en phrases françaises (« Annonce publiée : … », « Demande de messe SSM-2026-0012 marquée payée »).
 */
class ActivityObserver
{
    /** Libellé et genre (f/m) de chaque modèle suivi. */
    private const SUBJECTS = [
        Announcement::class      => ['Annonce', 'f'],
        Publication::class       => ['Publication', 'f'],
        Homily::class            => ['Homélie', 'f'],
        Event::class             => ['Événement', 'm'],
        Donation::class          => ['Don', 'm'],
        Listen::class            => ['Rendez-vous', 'm'],
        Service::class           => ['Mouvement', 'm'],
        Priest::class            => ['Prêtre', 'm'],
        HistoryMilestone::class  => ['Jalon', 'm'],
        ChurchProject::class     => ['Projet', 'm'],
        ScheduleException::class => ['Exception d’horaire', 'f'],
        TimeSlot::class          => ['Créneau', 'm'],
        User::class              => ['Utilisateur', 'm'],
    ];

    private const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    /** Colonnes techniques dont la seule modification n'est pas journalisée. */
    private const IGNORED = ['updated_at', 'likes_count', 'last_login_at', 'remember_token', 'imported_at'];

    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Mess             => ActivityLogger::log('created', $model, 'Demande de messe ' . $this->messRef($model) . ' reçue'),
            $model instanceof ParticipantEvent => $this->logRegistration($model),
            $model instanceof PublicationComment, $model instanceof Setting => null,
            default => $this->logGeneric('created', $model),
        };
    }

    public function updated(Model $model): void
    {
        $changes = array_diff(array_keys($model->getChanges()), self::IGNORED);
        if (!$changes) {
            return;
        }

        match (true) {
            $model instanceof Mess               => $this->logMess($model, $changes),
            $model instanceof PublicationComment => $this->logComment($model, $changes),
            $model instanceof Setting            => in_array('value', $changes, true)
                ? ActivityLogger::log('updated', $model, 'Paramètre modifié : ' . ($model->label ?: $model->key))
                : null,
            $model instanceof ParticipantEvent   => null,
            default => $this->logGeneric(in_array('status', $changes, true) && $model->getAttribute('status') !== null ? 'status_changed' : 'updated', $model),
        };
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Mess) {
            ActivityLogger::log('deleted', $model, 'Demande de messe ' . $this->messRef($model) . ' supprimée');
            return;
        }
        if ($model instanceof PublicationComment || $model instanceof ParticipantEvent || $model instanceof Setting) {
            return;
        }

        $this->logGeneric('deleted', $model);
    }

    /* ============== Phrases ============== */

    private function logGeneric(string $action, Model $model): void
    {
        [$label, $gender] = self::SUBJECTS[get_class($model)] ?? [class_basename($model), 'm'];
        $status = $model->getAttribute('status');

        // Création sans statut explicite : valeur par défaut de la colonne (ex. « published »)
        if ($action === 'created' && $status === null && $model->getKey()
            && \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'status')) {
            $status = $model->newQueryWithoutScopes()->whereKey($model->getKey())->value('status');
        }

        $verb = match ($action) {
            'deleted' => 'supprimé',
            'created' => $status === 'published' ? 'publié' : ($status === 'draft' ? 'créé (brouillon)' : 'créé'),
            'status_changed' => match ($status) {
                'published' => 'publié',
                'hidden'    => 'masqué',
                'draft'     => 'repassé en brouillon',
                'disabled', 'inactive' => 'désactivé',
                'active'    => 'réactivé',
                default     => 'modifié',
            },
            default => 'modifié',
        };

        ActivityLogger::log($action, $model, $label . ' ' . $this->agree($verb, $gender) . ' : ' . $this->name($model));
    }

    private function logRegistration(ParticipantEvent $participant): void
    {
        $event = $participant->event;
        $count = (int) ($participant->attendees ?? 1);

        ActivityLogger::log('created', $participant, sprintf(
            'Nouvelle inscription à « %s » (%d %s)',
            $event?->title ?? 'événement',
            $count,
            $count > 1 ? 'personnes' : 'personne'
        ));
    }

    private function logMess(Mess $mess, array $changes): void
    {
        $ref = 'Demande de messe ' . $this->messRef($mess);

        if (in_array('payment_status', $changes, true)) {
            $label = match ($mess->payment_status) {
                'succeeded' => 'marquée payée',
                'to_pay'    => 'marquée à régler au secrétariat',
                'failed'    => 'marquée non payée',
                default     => 'en attente de paiement',
            };
            ActivityLogger::log('status_changed', $mess, "$ref $label");
        }

        if (in_array('request_status', $changes, true)) {
            $label = match ($mess->request_status) {
                'accepted' => 'acceptée',
                'canceled' => 'annulée',
                default    => 'remise à traiter',
            };
            ActivityLogger::log('status_changed', $mess, "$ref $label");
        }

        if (!array_intersect($changes, ['payment_status', 'request_status'])) {
            ActivityLogger::log('updated', $mess, "$ref modifiée");
        }
    }

    private function logComment(PublicationComment $comment, array $changes): void
    {
        $title = $comment->publication?->title ?? 'une publication';

        if (in_array('status', $changes, true)) {
            $verb = match ($comment->status) {
                'published' => 'validé',
                'rejected'  => 'refusé',
                default     => 'remis en attente',
            };
            ActivityLogger::log('status_changed', $comment, "Commentaire $verb sur « $title »");
        } elseif (in_array('reply', $changes, true) && $comment->reply) {
            ActivityLogger::log('updated', $comment, "Réponse publiée à un commentaire sur « $title »");
        }
    }

    /* ============== Utilitaires ============== */

    /** Accord du participe passé (« publié » → « publiée »). */
    private function agree(string $verb, string $gender): string
    {
        if ($gender !== 'f') {
            return $verb;
        }

        return preg_replace('/^(\S+é)/u', '$1e', $verb);
    }

    /** Nom lisible de l'objet (jamais de coordonnées personnelles). */
    private function name(Model $model): string
    {
        return match (true) {
            $model instanceof Donation => number_format((float) $model->amount, 0, ',', ' ') . ' FCFA'
                . ($model->project ? ' (' . $model->project . ')' : ''),
            $model instanceof Listen => $model->type ?: 'demande n° ' . $model->id,
            $model instanceof Priest, $model instanceof User => $model->fullname ?: ($model->name ?? '#' . $model->id),
            $model instanceof HistoryMilestone => trim($model->year . ' – ' . $model->title),
            $model instanceof ScheduleException => trim(substr((string) $model->date, 0, 10) . ' ' . ($model->label ?? '')),
            $model instanceof TimeSlot => ($model->label ?: ucfirst((string) $model->type))
                . ' (' . (self::JOURS[(int) $model->weekday] ?? '') . ')',
            default => (string) ($model->getAttribute('title') ?? '#' . $model->getKey()),
        };
    }

    private function messRef(Mess $mess): string
    {
        return $mess->number ?: 'n° ' . $mess->id;
    }
}
