<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Policies\BlogPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        # Blog
        Gate::define('view-blog', [BlogPolicy::class, 'view']);
        Gate::define('create-blog', [BlogPolicy::class, 'create']);
        Gate::define('update-blog', [BlogPolicy::class, 'update']);
        Gate::define('delete-blog', [BlogPolicy::class, 'delete']);
    }
}
