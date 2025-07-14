<?php

namespace Kreatif\StatamicFragmentCache\Tags;

use Kreatif\StatamicFragmentCache\Facades\StatamicFragmentCache;

class CacheModule extends BaseCacheTag
{
    /**
     * The handle for the tag, e.g. `{{ cache_module key='...'}}`
     */
    protected static $handle = 'cache_module';

    /**
     * Implements the abstract method from BaseCacheTag.
     * The key is built from the module's context.
     */
    protected function buildBaseCacheKey(): ?string
    {
        $baseKey = $this->params->get('key');
        $type = $this->context->get('type');
        $moduleId = $this->context->get('id');
        $parentEntryId = $this->params->get('entry_id', $this->context->get('id'));
        if (!$type || !$moduleId || !$parentEntryId) {
            return !empty($baseKey) ? $baseKey : null;
        }
        return ($baseKey ? "{$baseKey}:":'')."{$parentEntryId}:{$type}:{$moduleId}";
    }

    public function getCacheKeyPrefix(): string {
        return StatamicFragmentCache::getPrefix('module');
    }
}
