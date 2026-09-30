<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ChurchProject;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Homily;
use App\Models\Listen;
use App\Models\LiturgyDay;
use App\Models\MassSchedule;
use App\Models\Mess;
use App\Models\PublicationComment;
use App\Models\TimeSlot;
use App\Services\MassRequestService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const TIMEZONE = 'Africa/Abidjan';

    /** Nombre de jours de textes AELF attendus à l'avance. */
    private const AELF_DAYS = 7;

    public function __construct(protected MassRequestService $masses)
    {
    }

    /**
     * Tableau de bord du back-office. GET /admin/dashboard
     */
    public function show(): JsonResponse
    {
        $now = Carbon::now(self::TIMEZONE);
        $today = $now->toDateString();
        $tomorrow = $now->copy()->addDay()->toDateString();

        $massesToProcess = Mess::where('request_status', 'pending')->count();
        $massesToPay = Mess::where('payment_status', 'to_pay')->count();

        $weekDonations = Donation::where('payment_status', 'succeeded')
            ->where('donation_at', '>=', $now->copy()->startOfWeek(Carbon::MONDAY)->setTimezone(config('app.timezone')));

        $pendingComments = PublicationComment::where('status', 'pending');
        $pendingListens = Listen::where('request_status', 'pending')->count();

        $importedDays = LiturgyDay::where('date', '>=', $today)->count();
        $homilyToday = $this->homilyState($today);
        $homilyTomorrow = $this->homilyState($tomorrow);

        $expiring = Announcement::published()
            ->whereNotNull('visible_until')
            ->whereBetween('visible_until', [$today, $now->copy()->addDays(2)->toDateString()])
            ->count();

        $project = ChurchProject::current();
        $collected = $project->collectedAmount();
        $currentPhase = collect($project->phases ?? [])->firstWhere('status', 'in_progress')['name'] ?? null;

        $tasks = array_values(array_filter([
            $massesToProcess ? $this->task('masses_to_process', $this->plural($massesToProcess, 'demande de messe à traiter', 'demandes de messe à traiter'), '/dashboard/messes?filtre=to_process') : null,
            ($n = (clone $pendingComments)->count()) ? $this->task('comments_pending', $this->plural($n, 'commentaire à modérer', 'commentaires à modérer'), '/dashboard/commentaires') : null,
            $pendingListens ? $this->task('listens_pending', $this->plural($pendingListens, 'rendez-vous en attente', 'rendez-vous en attente'), '/dashboard/rendez-vous') : null,
            $homilyTomorrow === 'missing' ? $this->task('homily_tomorrow_missing', 'Homélie de demain manquante', '/dashboard/liturgie') : null,
            $expiring ? $this->task('announcements_expiring', $this->plural($expiring, 'annonce expire dans 2 jours', 'annonces expirent dans 2 jours'), '/dashboard/annonces') : null,
            $importedDays < self::AELF_DAYS ? $this->task('aelf_missing', "Textes AELF : {$importedDays} jour(s) importé(s) sur " . self::AELF_DAYS, '/dashboard/liturgie') : null,
        ]));

        return response()->json(['data' => [
            'masses' => ['to_process' => $massesToProcess, 'to_pay' => $massesToPay],
            'donations_week' => [
                'total' => (int) (clone $weekDonations)->sum('amount'),
                'count' => (clone $weekDonations)->count(),
            ],
            'comments' => [
                'pending'      => (clone $pendingComments)->count(),
                'publications' => (clone $pendingComments)->distinct()->count('publication_id'),
            ],
            'listens' => ['pending' => $pendingListens],
            'next_event' => $this->nextEvent($today),
            'liturgy' => [
                'imported_days'   => $importedDays,
                'last_import_at'  => optional(LiturgyDay::max('imported_at') ? Carbon::parse(LiturgyDay::max('imported_at')) : null)->toDateTimeString(),
                'homily_today'    => $homilyToday,
                'homily_tomorrow' => $homilyTomorrow,
            ],
            'church_project' => [
                'progress'         => $project->progress($collected),
                'collected_amount' => $collected,
                'goal_amount'      => (int) $project->goal_amount,
                'current_phase'    => $currentPhase,
            ],
            'today_masses' => $this->todayMasses($now->copy()->startOfDay()),
            'tasks' => $tasks,
        ]]);
    }

    /** published | scheduled | draft | missing pour l'homélie d'une date. */
    private function homilyState(string $date): string
    {
        $states = Homily::where('date', $date)->get()->map->state();

        foreach (['published', 'scheduled', 'draft'] as $state) {
            if ($states->contains($state)) {
                return $state;
            }
        }

        return 'missing';
    }

    private function nextEvent(string $today): ?array
    {
        $event = Event::published()
            ->whereDate('date_at', '>=', $today)
            ->orderBy('date_at')->orderBy('time_at')
            ->first();

        if (!$event) {
            return null;
        }

        $participants = $event->activeParticipants()->get(['id', 'attendees']);

        return [
            'id'               => $event->id,
            'slug'             => $event->slug,
            'title'            => $event->title,
            'date_at'          => $event->dateString(),
            'registrations'    => $participants->count(),
            'attendees'        => (int) $participants->sum(fn ($p) => (int) ($p->attendees ?? 1)),
            'max_participants' => $event->max_participants,
        ];
    }

    private function todayMasses(Carbon $today): array
    {
        $date = $today->toDateString();

        return $this->masses->celebratedSlots($today)->map(function (TimeSlot $slot) use ($date) {
            $schedules = MassSchedule::where('date', $date)->where('time_slot_id', $slot->id)
                ->occupying()->with('mess:id,is_confidential')->get();

            return [
                'time_slot_id' => $slot->id,
                'time'         => MassRequestService::slotTime($slot),
                'label'        => $slot->label ?: 'Messe',
                'intentions'   => $schedules->count(),
                'confidential' => $schedules->filter(fn ($s) => $s->mess?->is_confidential)->count(),
            ];
        })->values()->all();
    }

    private function task(string $key, string $label, string $href): array
    {
        return ['key' => $key, 'label' => $label, 'href' => $href];
    }

    private function plural(int $n, string $singular, string $plural): string
    {
        return $n . ' ' . ($n > 1 ? $plural : $singular);
    }
}
