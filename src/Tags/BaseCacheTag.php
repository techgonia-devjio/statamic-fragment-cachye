<?php

namespace Devjio\StatamicFragmentCache\Tags;

use Devjio\StatamicFragmentCache\Support\CacheStack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Devjio\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;


/**
 * Abstract base class for creating smart, dependency-aware cache tags.
 * Provides core logic for caching, dependency tracking, and "donut hole" caching.
 *
 * @param string      key         A unique identifier for the cache entry. Required for `cache_fragment`.
 * @param string      entry_id    The ID of the parent entry. Automatically inferred in `cache_module`.
 * @param string|bool watch Dependencies to watch. `true` enables auto-watching. A `|` separated string of entry IDs for manual watching.
 * @param string      for A human-readable cache duration (e.g., "1 day", "2 hours"). Overrides the default.
 */
abstract class BaseCacheTag extends Tags
{

    protected $ignoreCachePlaceholders = [];

    abstract protected function buildBaseCacheKey(): ?string;

    abstract public function getCacheKeyPrefix(): string;

    abstract protected function getLivePreviewKeySuffix(): ?string;

    public function addIgnoredBlock(string $placeholder, string $content): void
    {
        $this->ignoreCachePlaceholders[$placeholder] = $content;
    }

    public function index()
    {
        if (!StatamicFragmentCache::isEnabled()) {
            return (string) Antlers::parse($this->content, $this->context->all());
        }

        $cacheKey = $this->buildCacheKey();
        if (!$cacheKey) {
            return $this->handleMissingKey();
        }

        CacheStack::push($this);
        $startTime = microtime(true);

        try {
            $cachedPayload = Cache::remember($cacheKey, $this->getCacheDuration(), function () use ($cacheKey) {
                // The `generateCachePayload` method will now be much simpler.
                return $this->generateCachePayload($cacheKey);
            });
        } finally {
            // Always ensure we pop the tag off the stack when we're done.
            CacheStack::pop();
        }

        if ($parent = CacheStack::peek()) {
            // If we are nested, pass our placeholders up to the parent.
            foreach ($cachedPayload['placeholders'] as $placeholder => $content) {
                $parent->addIgnoredBlock($placeholder, $content);
            }
            // And return our content with the placeholders still inside.
            StatamicFragmentCache::logger()->debug("Total execution time for key {$cacheKey}: " . $this->getDurationForCompute($startTime));
            return $cachedPayload['content'];
        }
        $content = $this->renderFromPayload($cachedPayload);
        StatamicFragmentCache::logger()->debug("Total execution time for key {$cacheKey}: " . $this->getDurationForCompute($startTime));

        return $content;

        /*
        // Check the cache first.
        if ($cachedPayload = Cache::get($cacheKey)) {
            StatamicFragmentCache::logger()->debug("Cache HIT for key: {$cacheKey}");
            $content = $this->renderFromPayload($cachedPayload);
        } else {
            // If not found, generate, cache, and then render.
            $cachedPayload = $this->generateCachePayload($cacheKey);
            Cache::put($cacheKey, $cachedPayload, $this->getCacheDuration());
            $content = $this->renderFromPayload($cachedPayload);
        }

        StatamicFragmentCache::logger()->debug("Total execution time for key {$cacheKey}: " . $this->getDurationForCompute($startTime));
        return $content;*/
    }

    protected function generateCachePayload(string $cacheKey): array
    {
        StatamicFragmentCache::logger()->info("Cache MISS for key: {$cacheKey}. Generating fresh content.");

        // The Antlers::parse call is now the key. As it parses, any `ignore_cache`
        // tags will execute their logic and call `addIgnoredBlock` on this instance,
        // populating the `$this->ignoreCachePlaceholders` array automatically.
        $parsedContent = (string) Antlers::parse($this->content, $this->context->all());
        if (!$this->isLivePreview()) {
            $this->storeDependencies($cacheKey);
            $this->addToMasterKeyIndex($cacheKey);
        }

        return [
            'content' => $parsedContent,
            'placeholders' => $this->ignoreCachePlaceholders,
        ];
    }

    protected function renderFromPayload(array $payload): string
    {
        $content = $payload['content'];
        $placeholders = $payload['placeholders'];

        if (empty($placeholders)) {
            return $content;
        }

        foreach ($placeholders as $placeholder => $originalContent) {
            $freshContent = (string) Antlers::parse($originalContent, $this->context->all());
            $content = str_replace($placeholder, $freshContent, $content);
        }

        return $content;
    }

    /**
     * Generates the data to be cached.
     */
    protected function generateCachePayload_legacy(string $cacheKey): array
    {
        $generationStartTime = microtime(true);
        StatamicFragmentCache::logger()->info("Cache MISS for key: {$cacheKey}. Generating fresh content.");

        $placeholders = [];
        $pattern = "/{{ *ignore_cache *}}(.*?){{ *\/ignore_cache *}}/si";
        $contentWithPlaceholders = preg_replace_callback($pattern, function ($matches) use (&$placeholders) {
            $placeholder = '<!--IGNORE_CACHE_PLACEHOLDER_'.Str::uuid().'-->';
            $placeholders[$placeholder] = $matches[1];
            return $placeholder;
        }, $this->content);

        $parsedContent = (string) Antlers::parse($contentWithPlaceholders, $this->context->all());

        if (!$this->isLivePreview()) {
            $this->storeDependencies($cacheKey);
        }

        $duration = $this->getDurationForCompute($generationStartTime);
        StatamicFragmentCache::logger()->debug("Cache CREATED for key: {$cacheKey}. Generation time: {$duration}");

        return [
            'content' => $parsedContent,
            'placeholders' => $placeholders,
        ];
    }

    /**
     * Renders the final HTML from a cache payload.
     */
    protected function renderFromPayload_legacy(array $payload): string
    {
        $content = $payload['content'];
        $placeholders = $payload['placeholders'];

        if (empty($placeholders)) {
            return $content;
        }

        foreach ($placeholders as $placeholder => $originalContent) {
            $freshContent = (string) Antlers::parse($originalContent, $this->context->all());
            $content = str_replace($placeholder, $freshContent, $content);
        }

        return $content;
    }

    public function addToMasterKeyIndex(string $cacheKey): void
    {
        // Should store the values in json format or is there a better way?
        // because saving files in json, could do a lot of I/O operations.

    }

    protected function storeDependencies(string $cacheKey): void
    {
        $dependencyTags = $this->buildWatchTagsList();
        if (empty($dependencyTags)) return;

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

        $full = "{$prefix}:{$currentLocale}:" . $baseKey . ($queryParams ? '?' . $queryParams : '');
        return $full;
    }

    protected function buildWatchTagsList(): array
    {
        $tags = [];
        $watchParam = $this->params->get('watch');
        if ($watchParam === true) {
            $watchVariables = StatamicFragmentCache::getAutoWatchVariablesList();
            foreach ($watchVariables as $var) {
                if ($this->context->has($var)) {
                    $entries = $this->context->get($var);
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
            return $livePreviewField && (($livePreviewField == true || !!$livePreviewField->value()));
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
