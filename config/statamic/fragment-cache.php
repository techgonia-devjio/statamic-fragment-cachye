<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | A single switch to enable or disable the entire fragment caching system.
    | This provides a quick way to debug caching issues without changing code.
    |
    */
    'enabled' => env('Statamic_FRAGMENT_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefixes
    |--------------------------------------------------------------------------
    |
    | Using unique, nested prefixes helps prevent collisions with other cached
    | items in your application and keeps the cache organized.
    |
    */
    'prefixes' => [
        'fragment'  => 'cache-fragment',
        'module'    => 'cache-module',
        'dependency_index' => 'dep-index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cache Duration
    |--------------------------------------------------------------------------
    |
    | The default lifetime for cached items. Can be overridden in the tag
    | with the `for="..."` parameter. Set to `null` to cache forever until
    | invalidated. Human-readable strings are supported (e.g., '1 day', '1 week').
    |
    */
    'default_duration' => null,

    /*
    |--------------------------------------------------------------------------
    | Automatic Dependency Watching
    |--------------------------------------------------------------------------
    |
    | When using `watch="true"`, the tag will look for variables with these
    | keys in the current context to automatically build its dependency list.
    | It will use the first one it finds.
    |
    */
    'auto_watch_variables' => [
        'children',
        // 'related_entries',
        // 'featured_article',
    ],


    'page_builder' => [
        // would be the bard name (ie. 'blocks' or 'modules' etc)
        'block_name' => 'modules'
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Invalidation
    |--------------------------------------------------------------------------
    |
    | Enable or disable the automatic cache flushing when an entry is saved.
    |
    */
    'invalidation' => [
        'enabled' => true,
        'invalidate_static_cache' => true, // For Statamic's half/full strategies
        /*
        |----------------------------------------------------------------------
        | Invalidation Cleanup Strategy (don't confuse with static cache)
        |----------------------------------------------------------------------
        |
        | Defines how thoroughly the cache is cleaned when an entry is saved.
        |
        | 'full': (Default) When a cached item is invalidated, the addon will
        | also remove its key from ALL other dependency lists it was
        | a part of. This might be the most "correct" behavior and prevents
        | stale cache index files, but requires more cache reads/writes, computation.
        |
        | 'simple': When an entry is saved, only its direct dependency list
        | is cleared. This is faster but may leave stale references
        | in other dependency lists.
        |
        */
        'cleanup_strategy' => 'simple',
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Preview
    |--------------------------------------------------------------------------
    |
    |
    */
    "live_preview" => [
        // Detect whether the page is rendering in live preview
        // there are options which works and don't works.
        // Online suggests to use header to detect live preview but that doesn't work
        // after dig deep found that context contain live_preview property when is live-previewing
        // header: request()->hasHeader('X-Statamic-Live-Preview') || request()->hasHeader('Statamic-Live-Preview')
        // context: $this->context->get('live_preview') != null
        "detect_using" => "context"
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for cache events. Useful for debugging.
    | Set `enabled` too false to disable all logging from this addon.
    | `channel` should correspond to a channel in your `logging.php` config.
    | `level` can be 'debug', 'info', 'warning', 'error', etc.
    |
    */
    'logging' => [
        'enabled' => env('STATAMIC_FRAGMENT_CACHE_LOGGING_ENABLED', true),
        'channel' => 'default',
        'level'   => 'info',
    ],

];
