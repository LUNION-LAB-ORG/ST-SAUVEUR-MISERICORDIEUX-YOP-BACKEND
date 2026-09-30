<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Journal d'activité du back-office
        foreach ([
            \App\Models\Announcement::class,
            \App\Models\Publication::class,
            \App\Models\PublicationComment::class,
            \App\Models\Homily::class,
            \App\Models\Event::class,
            \App\Models\ParticipantEvent::class,
            \App\Models\Mess::class,
            \App\Models\Donation::class,
            \App\Models\Listen::class,
            \App\Models\Service::class,
            \App\Models\Priest::class,
            \App\Models\HistoryMilestone::class,
            \App\Models\ChurchProject::class,
            \App\Models\Setting::class,
            \App\Models\ScheduleException::class,
            \App\Models\TimeSlot::class,
            \App\Models\User::class,
        ] as $model) {
            $model::observe(\App\Observers\ActivityObserver::class);
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
