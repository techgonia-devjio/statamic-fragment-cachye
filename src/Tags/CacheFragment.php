<?php

namespace Devjio\StatamicFragmentCache\Tags;

use Devjio\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Statamic\Facades\Antlers;

class CacheFragment extends BaseCacheTag
{
    /**
     * The handle for the tag, e.g. `{{ cache_fragment }}`
     */
    protected static $handle = 'cache_fragment';

    protected ?string $baseKey = null;

    protected function buildBaseCacheKey(): ?string
    {
        $key = $this->params->get('key');
        if (!$key) return null;

        // Would allow calculating the key always
        $computeKey = !!$this->params->get('computeKey');

        if (!$computeKey && !empty($this->baseKey)) {
            return $this->baseKey;
        }

        $this->baseKey =  (string) Antlers::parse($key, $this->context->all());
        return $this->baseKey;
    }

    public function getCacheKeyPrefix(): string {
        return StatamicFragmentCache::getPrefix('fragment');
    }

    protected function getLivePreviewKeySuffix(): string
    {
        $key = $this->buildBaseCacheKey();
        $livePreviewKey = $this->params->get('livePreviewKey');
        return 'live-preview:'.md5(($livePreviewKey??'') . $key);
    }

}
