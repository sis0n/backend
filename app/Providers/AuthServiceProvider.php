<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
use Carbon\Carbon;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies(); 

        Passport::tokensExpireIn(now()->addMinutes(15));

        Passport::refreshTokensExpireIn(now()->addDays(30));

        //pang personal lang
        // Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Passport::routes();        
    }
}
