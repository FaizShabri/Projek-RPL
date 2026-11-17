<?php

namespace App\Providers;

use App\Events\DisputeOpened;
use App\Events\OrderStatusChanged;
use App\Events\ReviewSubmitted;
use App\Events\TokoRegistered;
use App\Listeners\SendDisputeNotification;
use App\Listeners\SendOrderStatusNotification;
use App\Listeners\SendReviewNotification;
use App\Listeners\SendTokoRegisteredNotification;
use App\Policies\FinancePolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Finance Policy Gates
        Gate::define('manageFinances', [FinancePolicy::class, 'manageFinances']);
        Gate::define('manageCommissions', [FinancePolicy::class, 'manageCommissions']);
        Gate::define('manageSettlements', [FinancePolicy::class, 'manageSettlements']);

        // Register Notification Policy
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);

        // Register Event Listeners
        Event::listen(OrderStatusChanged::class, SendOrderStatusNotification::class);
        Event::listen(DisputeOpened::class, SendDisputeNotification::class);
        Event::listen(ReviewSubmitted::class, SendReviewNotification::class);
        Event::listen(TokoRegistered::class, SendTokoRegisteredNotification::class);
    }
}
