<?php

namespace App\Providers;

use App\Services\Tickets\OpenRouterTicketClassifier;
use App\Services\Tickets\TicketClassifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TicketClassifier::class, OpenRouterTicketClassifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
