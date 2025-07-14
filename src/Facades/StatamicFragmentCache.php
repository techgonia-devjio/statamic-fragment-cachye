<?php

namespace Kreatif\StatamicFragmentCache\Facades;

use Illuminate\Support\Facades\Facade;
use Kreatif\StatamicFragmentCache\Support\StatamicFragmentCacheLogger;


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
 * @see \Kreatif\StatamicFragmentCache\Support\StatamicFragmentCache
 */
class StatamicFragmentCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Kreatif\StatamicFragmentCache\Support\StatamicFragmentCache::class;
    }
}
