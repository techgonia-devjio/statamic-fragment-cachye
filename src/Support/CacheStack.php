<?php

namespace Devjio\StatamicFragmentCache\Support;

use Devjio\StatamicFragmentCache\Tags\BaseCacheTag;

class CacheStack
{

    protected static array $stack = [];

    public static function push(BaseCacheTag $tag): void
    {
        static::$stack[] = $tag;
    }

    public static function pop(): ?BaseCacheTag
    {
        return array_pop(static::$stack);
    }

    public static function peek(): ?BaseCacheTag
    {
        return end(static::$stack) ?: null;
    }

}