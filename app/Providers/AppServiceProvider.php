<?php

namespace App\Providers;

use App\Models\Category;
use App\Support\Currency;
use App\Support\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request.
        $this->app->scoped(Currency::class, fn ($app) => new Currency($app['request']));
        $this->app->scoped(Settings::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.default');

        // The header (mega menu, drawer) and footer list categories on every page.
        View::composer(
            ['components.site.header', 'components.site.footer'],
            fn ($view) => $view->with('navCategories', Category::navigation()),
        );
    }
}
