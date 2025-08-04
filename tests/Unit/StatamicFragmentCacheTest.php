<?php

namespace Devjio\StatamicFragmentCache\Tests\Unit;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Devjio\StatamicFragmentCache\Facades\StatamicFragmentCache;
use Devjio\StatamicFragmentCache\Tests\TestCase;
use Mockery;

class StatamicFragmentCacheTest extends TestCase
{
    /** @test */
    public function test_it_returns_correct_enabled_status()
    {
        config(['statamic.fragment-cache.enabled' => true]);
        $this->assertTrue(StatamicFragmentCache::isEnabled());

        config(['statamic.fragment-cache.enabled' => false]);
        $this->assertFalse(StatamicFragmentCache::isEnabled());
    }

    /** @test */
    public function test_it_returns_correct_prefix()
    {
        config(['statamic.fragment-cache.prefixes.fragment' => 'custom-fragment-prefix']);
        $this->assertEquals('custom-fragment-prefix', StatamicFragmentCache::getPrefix('fragment'));

        config(['statamic.fragment-cache.prefixes.dependency_index' => 'custom-dependency-prefix']);
        $this->assertEquals('custom-dependency-prefix', StatamicFragmentCache::getPrefix('dependency_index'));
    }

    /** @test */
    public function test_it_returns_correct_live_preview_detection_method()
    {
        config(['statamic.fragment-cache.live_preview.detect_using' => 'header']);
        $this->assertEquals('header', StatamicFragmentCache::getLivePreviewDetectionMethod());

        config(['statamic.fragment-cache.live_preview.detect_using' => 'context']);
        $this->assertEquals('context', StatamicFragmentCache::getLivePreviewDetectionMethod());
    }

    /** @test */
    public function test_it_returns_correct_page_builder_block_name()
    {
        config(['statamic.fragment-cache.page_builder.block_name' => 'my_modules']);
        $this->assertEquals('my_modules', StatamicFragmentCache::getPageBuilderBlockName());
    }

    /** @test */
    public function test_it_returns_correct_auto_watch_variables_list()
    {
        config(['statamic.fragment-cache.auto_watch_variables' => ['entry', 'page']]);
        $this->assertEquals(['entry', 'page'], StatamicFragmentCache::getAutoWatchVariablesList());
    }

    /** @test */
    public function test_it_returns_correct_invalidation_status()
    {
        config(['statamic.fragment-cache.invalidation.enabled' => true]);
        $this->assertTrue(StatamicFragmentCache::shouldInvalidate());

        config(['statamic.fragment-cache.invalidation.enabled' => false]);
        $this->assertFalse(StatamicFragmentCache::shouldInvalidate());
    }

    /** @test */
    public function test_it_returns_correct_static_cache_invalidation_status()
    {
        config(['statamic.fragment-cache.invalidation.invalidate_static_cache' => true]);
        $this->assertTrue(StatamicFragmentCache::shouldInvalidateStaticCache());

        config(['statamic.fragment-cache.invalidation.invalidate_static_cache' => false]);
        $this->assertFalse(StatamicFragmentCache::shouldInvalidateStaticCache());
    }

    /** @test */
    public function test_get_cache_duration_in_base_cache_tag()
    {
        // Mock a BaseCacheTag instance for testing the protected getCacheDuration method
        $tag = new class extends \Devjio\StatamicFragmentCache\Tags\BaseCacheTag {
            protected static $handle = 'test_tag';
            protected function buildBaseCacheKey(): ?string { return 'test'; }
            public function getCacheKeyPrefix(): string { return 'test'; }
            public function setContext($context) { $this->context = collect($context); return $this; }
            public function setParams($params) { $this->params = collect($params); return $this; }
            public function callGetCacheDuration() { return $this->getCacheDuration(); }
            protected function getLivePreviewKeySuffix(): ?string { return ''; }
        };

        config(['statamic.fragment-cache.default_duration' => null]);
        $tag->setContext([])->setParams([]);
        $this->assertNull($tag->callGetCacheDuration());
        config(['statamic.fragment-cache.default_duration' => '5 minutes']);
        $tag->setContext([])->setParams([]);
        $this->assertEquals((string) CarbonInterval::minutes(5), $tag->callGetCacheDuration());
        $tag->setContext([])->setParams(['for' => '1 hour']);
        $this->assertEquals((string) CarbonInterval::hours(1), $tag->callGetCacheDuration());
        config(['statamic.fragment-cache.live_preview.detect_using' => 'context']);
        $tag->setContext(['live_preview' => new class { public function value() { return true; } }])
            ->setParams(['for' => '1 day']);
        $this->assertEquals(CarbonInterval::seconds(5), $tag->callGetCacheDuration());
    }
}
