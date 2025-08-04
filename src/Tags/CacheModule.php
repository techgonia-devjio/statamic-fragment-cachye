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
     * The key is built from the module's context.
     * It does not use the `key` parameter.
     */
    protected function buildBaseCacheKey(): ?string
    {
        $type = $this->context->get('type') ?? $this->context->get('handle');
        $moduleId = $this->context->get('id');
        $parentEntryId = $this->params->get('entry_id');
        if (!$type || !$moduleId || !$parentEntryId) {
            return null; // A module must have these to be cached
        }
        return "{$parentEntryId}:{$type}:{$moduleId}";
    }

    public function getCacheKeyPrefix(): string {
        return StatamicFragmentCache::getPrefix('module');
    }

    /**
     * Overrides the base hook method to provide a unique suffix for Live Preview.
     */
    protected function getLivePreviewKeySuffix(): string
    {
        $blockName = StatamicFragmentCache::getPageBuilderBlockName();
        // Trying to avoid the use of $this->context->all() because it falls in recursion or shows white page
        $moduleData = $this->context->get($blockName)?->value() ?? [];
        // TODO: remove this module data, use something else maybe just entry id or...
        $moduleData['title'] = $this->context->get('title')?->value() ?? null;
        $moduleData['subtitle'] = $this->context->get('subtitle')?->value() ?? null;
        ksort($moduleData);
        return ':live-preview:'.md5(json_encode($moduleData));
    }
}
