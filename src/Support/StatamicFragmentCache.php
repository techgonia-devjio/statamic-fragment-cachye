<?php

namespace Kreatif\StatamicFragmentCache\Support;

class StatamicFragmentCache
{
    public function isEnabled(): bool
    {
        return config('statamic.fragment-cache.enabled', true);
    }

    public function getPrefix(string $type): string
    {
        return config("statamic.fragment-cache.prefixes.{$type}", "kreatif-cache-{$type}");
    }

    public function getLivePreviewDetectionMethod(): string
    {
        return config('statamic.fragment-cache.live_preview.detect_using', 'context');
    }

    public function getPageBuilderBlockName(): string {
        return config('statamic.fragment-cache.page_builder.block_name', 'modules');
    }

    public function getAutoWatchVariablesList(): array {
        return config('statamic.fragment-cache.auto_watch_variables', []);
    }

    public function shouldInvalidate(): bool
    {
        return config('statamic.fragment-cache.invalidation.enabled', true);
    }

    public function shouldInvalidateStaticCache(): bool
    {
        return config('statamic.fragment-cache.invalidation.invalidate_static_cache', true);
    }

    public function logEnabled(): bool {
        return config('statamic.fragment-cache.logging.enabled', false);
    }

    public function logger(): StatamicFragmentCacheLogger
    {
        return app(StatamicFragmentCacheLogger::class);
    }

}
