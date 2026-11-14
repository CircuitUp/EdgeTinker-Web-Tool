<?php

namespace App\Providers;

use App\Models\Category;
use App\Search\MySqlProductSearch;
use App\Search\ProductSearch;
use App\Studio\ContentTypes\CategoryType;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\ContentTypes\ProductType;
use App\Studio\ContentTypes\TagType;
use App\Support\Currency;
use App\Support\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Always built from the current request, so it can never carry one
        // visitor's (or one test request's) currency over to the next.
        $this->app->bind(Currency::class, fn ($app) => new Currency($app['request']));

        // One instance per request.
        $this->app->scoped(Settings::class);

        // Swap this binding to move search to a dedicated engine later.
        $this->app->bind(ProductSearch::class, MySqlProductSearch::class);

        // Content that goes through the approval workflow. To add a new kind of
        // content, write a ContentType class and register it here.
        $this->app->singleton(ContentTypeRegistry::class, function () {
            $registry = new ContentTypeRegistry;
            foreach ([ProductType::class, CategoryType::class, TagType::class] as $type) {
                $registry->register(new $type);
            }

            return $registry;
        });
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
