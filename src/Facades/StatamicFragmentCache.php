<?php

namespace Devjio\StatamicFragmentCache\Facades;

use Illuminate\Support\Facades\Facade;
use Devjio\StatamicFragmentCache\Support\StatamicFragmentCacheLogger;


/**
 * @method static bool isEnabled()
 * @method static string getPrefix(string $type)
 * @method static string getLivePreviewDetectionMethod()
 * @method static string getPageBuilderBlockName()
 * @method static array getAutoWatchVariablesList()
 * @method static bool shouldInvalidate()
 * @method static bool shouldInvalidateStaticCache()
 * @method static StatamicFragmentCacheLogger logger()
 *
 *
 * @see \Devjio\StatamicFragmentCache\Support\StatamicFragmentCache
 */
class StatamicFragmentCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Devjio\StatamicFragmentCache\Support\StatamicFragmentCache::class;
    }
}
