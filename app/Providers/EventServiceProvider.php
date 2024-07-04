<?php

namespace App\Providers;

use App\Models\Septic\StBooking;
use App\Models\WtBooking;
use App\Observers\SepticTanker\StBookingObserver;
use App\Observers\WaterTanker\WtBookingObserver;
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
        'App\Events\WaterTanker\EventWaterTanker' => [
            'App\Listeners\WaterTanker\SendWhatsAppMessage',
        ],
        'App\Events\SepticTanker\EventSepticTanker' => [
            'App\Listeners\SepticTanker\SendWhatsAppMessage',
        ],
        'App\Events\WaterTanker\EventWaterTanker' => [
            'App\Listeners\WaterTanker\SendMessage',
        ],
        'App\Events\SepticTanker\EventSepticTanker' => [
            'App\Listeners\SepticTanker\SendMessage',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        WtBooking::observe(WtBookingObserver::class);
        StBooking::observe(StBookingObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
