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

        // Sprint 4 — Communication Engine (ADR-016). Listeners are forward-only:
        // they hand the domain event to the dispatcher (queue) and hold no
        // business logic.
        \App\Events\PaymentStatusChanged::class => [
            \App\Listeners\Communication\PaymentStatusChangedListener::class,
        ],
        \App\Events\TicketIssued::class => [
            \App\Listeners\Communication\TicketIssuedListener::class,
        ],
        \App\Events\TicketStatusChanged::class => [
            \App\Listeners\Communication\TicketStatusChangedListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
