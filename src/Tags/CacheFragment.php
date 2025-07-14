<?php

namespace Kreatif\StatamicFragmentCache\Tags;

use Kreatif\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Statamic\Facades\Antlers;

class CacheFragment extends BaseCacheTag
{
    /**
     * The handle for the tag, e.g. `{{ cache_fragment }}`
     */
    protected static $handle = 'cache_fragment';

    protected function buildBaseCacheKey(): ?string
    {
        $key = $this->params->get('key');
        if (!$key) return null;

        return (string) Antlers::parse($key, $this->context->all());
    }

    public function getCacheKeyPrefix(): string {
        return StatamicFragmentCache::getPrefix('fragment');
    }

}
