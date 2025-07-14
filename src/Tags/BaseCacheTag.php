<?php

namespace Kreatif\StatamicFragmentCache\Tags;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Kreatif\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;


/**
 * Abstract base class for creating smart, dependency-aware cache tags.
 * Provides core logic for caching, dependency tracking, and "donut hole" caching.
 *
 * @param string  key         A unique identifier for the cache entry. Required for `cache_fragment`.
 * @param string  entry_id    The ID of the parent entry. Automatically inferred in `cache_module`.
 * @param string|bool watch Dependencies to watch. `true` enables auto-watching. A `|` separated string of entry IDs for manual watching.
 * @param string  for A human-readable cache duration (e.g., "1 day", "2 hours"). Overrides the default.
 */
abstract class BaseCacheTag extends Tags
{


    abstract protected function buildBaseCacheKey(): ?string;

    abstract public function getCacheKeyPrefix(): string;

    public function index()
    {
        if (!StatamicFragmentCache::isEnabled()) {
            return (string) Antlers::parse($this->content, $this->context->all());
        }

        $startTime = microtime(true);
        $cacheKey = $this->buildCacheKey();

        if (!$cacheKey) {
            return $this->handleMissingKey();
        }

        $cachedPayload = Cache::remember($cacheKey, $this->getCacheDuration(), function () use ($cacheKey) {
            $generationStartTime = microtime(true);
            StatamicFragmentCache::logger()->info("Cache MISS for key: {$cacheKey}. Generating fresh content.");

            $cachingData = $this->replaceIgnoreCacheBlocks();
            $contentWithPlaceholders = $cachingData['content'];
            $ignoreCachePlaceholders = $cachingData['placeholders'];
            $parsedContentWithPlaceholders = (string) Antlers::parse($contentWithPlaceholders, $this->context->all());

            if (!$this->isLivePreview()) {
                $this->storeDependencies($cacheKey);
            }

            $duration = $this->getDurationForCompute($generationStartTime);
            StatamicFragmentCache::logger()->debug("Cache CREATED for key: {$cacheKey}. Generation time: {$duration}");
            return [
                'content' => $parsedContentWithPlaceholders,
                'placeholders' => $ignoreCachePlaceholders,
            ];
        });
        $content = $cachedPayload['content'];
        $placeholders = $cachedPayload['placeholders'];
        if (!empty($placeholders)) {
            foreach ($placeholders as $placeholder => $originalContent) {
                $freshContent = (string) Antlers::parse($originalContent, $this->context->all());
                $content = str_replace($placeholder, $freshContent, $content);
            }
        }
        StatamicFragmentCache::logger()->debug("Total execution time for key {$cacheKey}: " . $this->getDurationForCompute($startTime));
        return $content;
    }

    protected function replaceIgnoreCacheBlocks(): array
    {
        $placeholders = [];
        $pattern = "/{{ *ignore_cache *}}(.*?){{ *\/ignore_cache *}}/si";
        $contentWithPlaceholders = preg_replace_callback($pattern, function ($matches) use (&$placeholders) {
            $placeholder = '<!--IGNORE_CACHE_PLACEHOLDER_'.Str::uuid().'-->';
            $placeholders[$placeholder] = $matches[1]; // Store the raw inner content
            return $placeholder;
        }, $this->content);
        return [
            'placeholders' => $placeholders,
            'content' => $contentWithPlaceholders
        ];
    }

    protected function storeDependencies(string $cacheKey): void
    {
        $dependencyTags = $this->buildWatchTagsList();
        if (empty($dependencyTags)) return;

        // Store the reverse look-up (cleaning strategy)
        // e.g., 'deps-for:cache-key-123' => ['entry:abc', 'entry:xyz']
        $reverseIndexKey = StatamicFragmentCache::getPrefix('dependency_index') . ':keys:' . $cacheKey;
        Cache::forever($reverseIndexKey, $dependencyTags);

        foreach ($dependencyTags as $tag) {
            $indexKey = StatamicFragmentCache::getPrefix('dependency_index') . ':' . $tag;
            $dependents = Cache::get($indexKey, []);
            if (!in_array($cacheKey, $dependents)) {
                $dependents[] = $cacheKey;
            }
            Cache::forever($indexKey, $dependents);
        }
    }

    protected function buildCacheKey(): ?string
    {
        $baseKey = $this->buildBaseCacheKey();
        if (!$baseKey) return null;

        if ($this->isLivePreview()) {
            $baseKey .= $this->getLivePreviewKeySuffix();
        }

        $cacheableGetParams = array_filter(explode(',', $this->params->get('cacheable_get_params', '')));
        $queryParams = http_build_query(request()->only($cacheableGetParams));

        $currentLocale = Site::current()->handle();
        $prefix = $this->getCacheKeyPrefix();
        $fullKey = "{$prefix}:{$currentLocale}:" . $baseKey . ($queryParams ? '?' . $queryParams : '');
        return $fullKey;
    }

    protected function getLivePreviewKeySuffix(): string
    {
        $blockName = StatamicFragmentCache::getPageBuilderBlockName();
        // Trying to avoid the use of $this->context->all() because it falls in recursion or shows white page
        $moduleData = $this->context->get($blockName)?->value() ?? [];
        // TODO: remove this module data, use something else maybe just entry id or...
        $moduleData['title'] = $this->context->get('title')?->value() ?? null;
        $moduleData['subtitle'] = $this->context->get('subtitle')?->value() ?? null;
        ksort($moduleData);
        $livePreviewHash = 'live-preview:'.md5(json_encode($moduleData));
        return $livePreviewHash;
    }

    protected function buildWatchTagsList(): array
    {
        // TODO: in future, improve also for the globals
        $tags = [];
        $watchParam = $this->params->get('watch');
        if ($watchParam === true) {
            $watchVariables = StatamicFragmentCache::getAutoWatchVariablesList();
            foreach ($watchVariables as $var) {
                if ($this->context->has($var)) {
                    $entries = $this->context->get($var);
                    // TODO: in future, improve this... $entries can be entries or also a field value..
                    if ($entries && is_iterable($entries)) {
                        foreach ($entries as $entry) {
                            if (isset($entry['id'])) {
                                $tags[] = 'entry:' . $entry['id'];
                            }
                        }
                    }
                    break;
                }
            }
        } elseif (is_string($watchParam)) {
            $watchedTags = array_filter(explode('|', (string) Antlers::parse($watchParam, $this->context->all())));
            $tags = array_merge($tags, $watchedTags);
        }

        if ($id = $this->params->get('entry_id', $this->context->get('id'))) {
            $tags[] = 'entry:' . $id;
        }

        return array_unique($tags);
    }

    protected function isLivePreview(): bool
    {
        $detectionMethod = StatamicFragmentCache::getLivePreviewDetectionMethod();
        if ($detectionMethod === 'context') {
            $livePreviewField = $this->context->get('live_preview');
            return $livePreviewField ? !!$livePreviewField->value() : false;
        }
        return request()->hasHeader('Statamic-Live-Preview') || request()->hasHeader('X-Statamic-Live-Preview');
    }

    protected function getCacheDuration(): ?\Carbon\CarbonInterval
    {
        if ($this->isLivePreview()) {
            return \Carbon\CarbonInterval::seconds(5);
        }
        if ($duration = $this->params->get('for')) {
            return \Carbon\CarbonInterval::make($duration);
        }
        if ($default = config('statamic.fragment-cache.default_duration')) {
            return \Carbon\CarbonInterval::make($default);
        }
        return null;
    }

    protected function handleMissingKey(): string
    {
        StatamicFragmentCache::logger()->warning("A required `key` parameter was missing or null.", [
            'context' => $this->context->all()
        ]);
        if (app()->environment('local')) {
            return '<div style="background:red;color:#ffffff;padding:40px;">FragmentCache: `key` is required.</div>';
        }
        return (string) Antlers::parse($this->content, $this->context->all());
    }

    protected function getDurationForCompute(float $startTime, string $unit = 'ms'): string
    {
        return round((microtime(true) - $startTime) * 1000) . $unit;
    }
}
