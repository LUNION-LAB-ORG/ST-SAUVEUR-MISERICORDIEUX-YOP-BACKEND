<?php

namespace App\Providers;

use App\Repositories\NewRepository;
use App\Repositories\MessRepository;
use App\Repositories\UserRepository;
use App\Repositories\EventRepository;
use App\Repositories\ListenRepository;
use App\Repositories\PastorRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Support\ServiceProvider;
use App\Repositories\DonationRepository;
use App\Repositories\TimeSlotRepository;
use App\Repositories\MediationRepository;
use App\Repositories\ProgrammationRepository;
use App\Repositories\ParticipantEventRepository;
use App\Repositories\Contracts\NewRepositoryInterface;
use App\Repositories\Contracts\MessRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\ListenRepositoryInterface;
use App\Repositories\Contracts\PastorRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\DonationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\MediationRepositoryInterface;
use App\Repositories\Contracts\ProgrammationRepositoryInterface;
use App\Repositories\Contracts\ParticipantEventRepositoryInterface;
use App\Repositories\PriestRepository;
use App\Repositories\Contracts\PriestRepositoryInterface;
use App\Repositories\HomilyRepository;
use App\Repositories\Contracts\HomilyRepositoryInterface;
use App\Repositories\HistoryMilestoneRepository;
use App\Repositories\Contracts\HistoryMilestoneRepositoryInterface;
use App\Repositories\AnnouncementRepository;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use App\Repositories\ScheduleExceptionRepository;
use App\Repositories\Contracts\ScheduleExceptionRepositoryInterface;
use App\Repositories\WhatsappSubscriberRepository;
use App\Repositories\Contracts\WhatsappSubscriberRepositoryInterface;
use App\Repositories\PublicationRepository;
use App\Repositories\Contracts\PublicationRepositoryInterface;
use App\Repositories\PublicationCommentRepository;
use App\Repositories\Contracts\PublicationCommentRepositoryInterface;
use App\Repositories\CouncilRepository;
use App\Repositories\Contracts\CouncilRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DonationRepositoryInterface::class, DonationRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(ListenRepositoryInterface::class, ListenRepository::class);
        $this->app->bind(MediationRepositoryInterface::class, MediationRepository::class);
        $this->app->bind(MessRepositoryInterface::class, MessRepository::class);
        $this->app->bind(NewRepositoryInterface::class, NewRepository::class);
        $this->app->bind(PastorRepositoryInterface::class, PastorRepository::class);
        $this->app->bind(ProgrammationRepositoryInterface::class, ProgrammationRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(TimeSlotRepositoryInterface::class, TimeSlotRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ParticipantEventRepositoryInterface::class, ParticipantEventRepository::class);

        // Refonte de l'accueil
        $this->app->bind(PriestRepositoryInterface::class, PriestRepository::class);
        $this->app->bind(HomilyRepositoryInterface::class, HomilyRepository::class);
        $this->app->bind(HistoryMilestoneRepositoryInterface::class, HistoryMilestoneRepository::class);
        $this->app->bind(AnnouncementRepositoryInterface::class, AnnouncementRepository::class);
        $this->app->bind(ScheduleExceptionRepositoryInterface::class, ScheduleExceptionRepository::class);
        $this->app->bind(WhatsappSubscriberRepositoryInterface::class, WhatsappSubscriberRepository::class);

        // Sous-pages (lot 2)
        $this->app->bind(PublicationRepositoryInterface::class, PublicationRepository::class);
        $this->app->bind(PublicationCommentRepositoryInterface::class, PublicationCommentRepository::class);
        $this->app->bind(CouncilRepositoryInterface::class, CouncilRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
