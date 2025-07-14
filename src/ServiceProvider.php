<?php

namespace Kreatif\StatamicFragmentCache;


use Kreatif\StatamicFragmentCache\Listeners\FlushEntryCache;
use Kreatif\StatamicFragmentCache\Support\StatamicFragmentCache;
use Kreatif\StatamicFragmentCache\Support\StatamicFragmentCacheLogger;
use Kreatif\StatamicFragmentCache\Tags\CacheFragment;
use Kreatif\StatamicFragmentCache\Tags\CacheModule;
use Kreatif\StatamicFragmentCache\Tags\IgnoreCache;
use Statamic\Events\EntrySaved;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        CacheFragment::class,
        CacheModule::class,
        IgnoreCache::class,
    ];

    protected $listen = [
        EntrySaved::class => [
            FlushEntryCache::class,
        ],
    ];

    public function register()
    {
        $this->app->singleton(StatamicFragmentCache::class, function () {
            return new StatamicFragmentCache();
        });

        $this->app->singleton(StatamicFragmentCacheLogger::class, function () {
            return new StatamicFragmentCacheLogger();
        });

        $this->mergeConfigFrom(__DIR__.'/../config/statamic/fragment-cache.php', 'statamic.fragment-cache');
    }

    public function bootAddon()
    {
        $this->publishes([
            __DIR__.'/../config/statamic/fragment-cache.php' => config_path('statamic/fragment-cache.php'),
        ], 'fragment-cache-config');
    }
}
