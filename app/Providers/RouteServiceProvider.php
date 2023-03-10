<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';
    protected $namespace = 'App\Http\Controllers';
    protected $adminNamespace = 'App\Http\Controllers\Admin';
    protected $userNamespace = 'App\Http\Controllers\User';
    protected $recruiterNamespace = 'App\Http\Controllers\Recruiter';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('/')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::prefix('admin')
                ->middleware('api')
                ->name('admin.')
                ->namespace($this->adminNamespace)
                ->group(base_path('routes/admin.php'));

            Route::prefix('user')
                ->middleware('api')
                ->name('user.')
                ->namespace($this->userNamespace)
                ->group(base_path('routes/user.php'));

            Route::prefix('recruiter')
                ->middleware('api')
                ->name('recruiter.')
                ->namespace($this->recruiterNamespace)
                ->group(base_path('routes/recruiter.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(env('MAX_REQUEST_PER_MIN', 180))->by($request->user() ? $request->user()->id : $request->ip());
        });
    }
}
