<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Passport::tokensCan([
            'admin' => 'Access admin routes',
            'subadmin' => 'Access subadmin routes',
            'customer' => 'Access customer routes',
            'rider' => 'Access rider routes',
        ]);

        Passport::setDefaultScope([
            'customer',
        ]);

        // Optionally: enable routes if not already registered in a separate file
        // Passport::routes();
    }
}
