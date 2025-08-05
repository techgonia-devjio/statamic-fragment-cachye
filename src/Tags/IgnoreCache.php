<?php

namespace Devjio\StatamicFragmentCache\Tags;
use Devjio\StatamicFragmentCache\Support\CacheStack;
use Illuminate\Support\Str;
use Statamic\Facades\Antlers;
use Statamic\Tags\Tags;

class IgnoreCache extends Tags
{

    protected static $handle = 'ignore_cache';

    /**
     * @Usage: {{ ignore_cache }}
     * @return mixed
     */
    public function index()
    {
        // Check if we are inside an active cache tag
        if ($parentCacheTag = CacheStack::peek()) {
            $placeholder = '<!--IGNORE_CACHE_PLACEHOLDER_'.Str::uuid().'-->';
            $parentCacheTag->addIgnoredBlock($placeholder, $this->content);
            return $placeholder;
        }
        // in case this tag is used outside a cache tag,
        // we still want to return the content
        return (string) Antlers::parse($this->content, $this->context->all());
    }
}
