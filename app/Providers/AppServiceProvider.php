<?php

namespace App\Providers;

use App\Models;
use App\Models\User;
use App\Support\Auth\ApiTokens;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Nama pendek di kolom *_type (stock_movements.reference, activity_logs.subject).
        Relation::enforceMorphMap([
            'user' => User::class,
            'region' => Models\Region::class,
            'depot' => Models\Depot::class,
            'territory' => Models\Territory::class,
            'employee' => Models\Employee::class,
            'outlet_channel' => Models\OutletChannel::class,
            'outlet' => Models\Outlet::class,
            'sales_route' => Models\SalesRoute::class,
            'product_category' => Models\ProductCategory::class,
            'product' => Models\Product::class,
            'product_price' => Models\ProductPrice::class,
            'stock_location' => Models\StockLocation::class,
            'stock_document' => Models\StockDocument::class,
            'trip' => Models\Trip::class,
            'sale' => Models\Sale::class,
            'customer_return' => Models\CustomerReturn::class,
            'container_movement' => Models\ContainerMovement::class,
            'visit' => Models\Visit::class,
            'attendance' => Models\Attendance::class,
            'settlement' => Models\Settlement::class,
            'target' => Models\Target::class,
            'incentive_scheme' => Models\IncentiveScheme::class,
            'incentive_payout' => Models\IncentivePayout::class,
            'role' => Models\Role::class,
        ]);

        // Izin dari peran: @can('outlet.view'), middleware can:outlet.view, Gate::allows(...).
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ?: null);

        Auth::viaRequest('api-token', fn (Request $request) => ApiTokens::resolve($request));

        RateLimiter::for('mobile', fn (Request $request) => Limit::perMinute(config('mobile.rate_per_minute'))
            ->by('mobile:'.(ApiTokens::current($request)?->id ?? $request->ip())));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('username')));
    }
}
