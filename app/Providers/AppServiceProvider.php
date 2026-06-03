<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Entity;
use App\Models\Federation;
use App\Observers\EntityObserver;
use App\Policies\EntityPolicy;
use App\Policies\FederationPolicy;
use App\Services\Auth\FederationScopeService;
use App\Services\Auth\SamlService;
use App\Services\Auth\SamlServiceInterface;
use App\Services\Metadata\RuleRegistry;
use App\Services\Signing\SigningDriverFactory;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SamlServiceInterface::class, SamlService::class);

        $this->app->singleton(RuleRegistry::class, fn () => new RuleRegistry(
            rulesPath:      app_path('Services/Metadata/Rules'),
            rulesNamespace: 'App\\Services\\Metadata\\Rules',
        ));

        // Scoped = one instance per HTTP request (safe for Octane / Horizon)
        $this->app->scoped(FederationScopeService::class);

        $this->app->singleton(SigningDriverFactory::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Entity::observe(EntityObserver::class);

        $schemasDir = storage_path('app/schemas');
        if (! is_dir($schemasDir)) {
            mkdir($schemasDir, 0755, true);
        }

        Gate::policy(Federation::class, FederationPolicy::class);
        Gate::policy(Entity::class, EntityPolicy::class);
    }
}
