<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Order::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Ticket::class => \App\Policies\TicketPolicy::class,
        \App\Models\CommunicationLog::class => \App\Policies\CommunicationLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
