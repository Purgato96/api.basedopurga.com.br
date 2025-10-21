<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\Room;
use App\Policies\MessagePolicy;
use App\Policies\RoomPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;

class AuthServiceProvider extends BaseAuthServiceProvider {
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Room::class => RoomPolicy::class,
        Message::class => MessagePolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {
        $this->registerPolicies();
    }
}
