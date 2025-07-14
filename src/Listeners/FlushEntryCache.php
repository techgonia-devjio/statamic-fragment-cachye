<?php

namespace Kreatif\StatamicFragmentCache\Listeners;

use Illuminate\Support\Facades\Cache;
use Kreatif\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Statamic\Events\EntrySaved;
use Statamic\StaticCaching\Cacher;

class FlushEntryCache
{
    public function handle(EntrySaved $event): void
    {
        if (!StatamicFragmentCache::shouldInvalidate()) {
            return;
        }

        $dependencyTag = 'entry:' . $event->entry->id();
        $indexKey = StatamicFragmentCache::getPrefix('dependency_index') . ':' . $dependencyTag;

        $keysToForget = Cache::get($indexKey, []);

        if (!empty($keysToForget)) {
            StatamicFragmentCache::logger()
                ->info("Invalidating " . count($keysToForget) . " cache keys for entry: {$event->entry->id()}");

            foreach ($keysToForget as $key) {
                Cache::forget($key); //forget the actual cached content

                // If the strategy is 'full', perform the deep cleanup.
                if (config('statamic.fragment-cache.invalidation.cleanup_strategy') === 'full') {
                    $this->fullCleanup($key);
                }
            }
            Cache::forget($indexKey);
        }

        if (StatamicFragmentCache::shouldInvalidateStaticCache() && config('statamic.static_caching.strategy')) {
            app(Cacher::class)->invalidateUrl($event->entry->url());
            StatamicFragmentCache::logger()->info("Invalidated static cache for URL: {$event->entry->url()}");
        }
    }

    protected function fullCleanup(string $cacheKey): void
    {
        $reverseIndexKey = StatamicFragmentCache::getPrefix('dependency_index') . ':keys:' . $cacheKey;
        $allDependencies = Cache::get($reverseIndexKey, []);

        foreach ($allDependencies as $depTag) {
            $otherIndexKey = StatamicFragmentCache::getPrefix('dependency_index') . ':' . $depTag;
            $otherDependents = Cache::get($otherIndexKey, []);

            if (($k = array_search($cacheKey, $otherDependents)) !== false) {
                unset($otherDependents[$k]);
            }

            if (empty($otherDependents)) {
                Cache::forget($otherIndexKey);
            } else {
                Cache::forever($otherIndexKey, array_values($otherDependents));
            }
        }
        Cache::forget($reverseIndexKey);
    }
}
