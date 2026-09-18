<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(apiKey: config('services.anthropic.key')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditing();
    }

    /**
     * Record sign-in activity in the audit log.
     */
    protected function configureAuditing(): void
    {
        Event::listen(Login::class, fn (Login $event) => AuditLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'action' => 'login',
            'ip' => request()->ip(),
        ]));

        Event::listen(Logout::class, fn (Logout $event) => AuditLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'action' => 'logout',
            'ip' => request()->ip(),
        ]));

        Event::listen(Failed::class, fn (Failed $event) => AuditLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'action' => 'login_failed',
            'subject' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 255),
            'ip' => request()->ip(),
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
