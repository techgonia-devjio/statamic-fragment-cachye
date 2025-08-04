<?php

namespace Devjio\StatamicFragmentCache\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Devjio\StatamicFragmentCache\Tests\TestCase;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;

class CacheFragmentTagTest extends TestCase
{
    public function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function getCacheKey($key='test-key', $prefix = 'cache-fragment') {
        $currentLocale = Site::current()->handle();
        $key = "$prefix:$currentLocale:$key";
        return $key;
    }

    /** @test */
    public function test_it_caches_content()
    {
        $key = $this->getCacheKey();
        $this->assertFalse(Cache::has($key));

        $template = '{{ cache_fragment key="test-key" }} MY KEY IS something {{ /cache_fragment }}';
        $output1 = (string) Antlers::parse($template);
        $this->assertTrue(Cache::has($key));
        $cachedData = Cache::get($key)['content'];
        $this->assertEquals($output1, $cachedData);

        $output2 = (string) Antlers::parse($template);
        $this->assertEquals($output1, $output2);
    }

    /** @test */
    public function test_it_respects_cache_duration()
    {
        $key = $this->getCacheKey();
        $template = '{{ cache_fragment key="test-key" for="1 second" }}{{ [1,2,3,4,5,6,7,8,9,10,11,21,31,14,15,16,17,18,19,20,30,21,45,157,14] | random }}{{ /cache_fragment }}';

        $output1 = (string) Antlers::parse($template);
        $this->assertTrue(Cache::has($key));

        sleep(2);

        $output2 = (string) Antlers::parse($template);
        $this->assertNotEquals($output1, $output2);
    }

    /** @test */
    public function test_it_caches_computed_value()
    {
        $key = $this->getCacheKey("test-key-824");
        $template = '{{ cache_fragment key="test-key-824" }} {{ [1,2,3,4,5,6,7,8,9,10,11,21,31,14,15,16,17,18,19,20,30,21,45,157,14] | random }} {{ /cache_fragment }}';
        $output1 = (string) Antlers::parse($template);
        $this->assertTrue(Cache::has($key));
        $cachedData = Cache::get($key)['content'];
        $this->assertEquals($output1, $cachedData);
    }

    /** @test */
    public function test_it_does_not_cache_when_disabled()
    {
        $key = $this->getCacheKey();
        config(['statamic.fragment-cache.enabled' => false]);

        $template = '{{ cache_fragment key="test-key" }}{{ [1,2,3,4,5,6,7,8,9,10,11,21,31,14,15,16,17,18,19,20,30,21,45,157,14] | random }}{{ /cache_fragment }}';
        (string) Antlers::parse($template);

        $this->assertFalse(Cache::has($key));
    }

    /** @test */
    public function test_it_handles_missing_key_parameter()
    {
        app()->detectEnvironment(function () {
            return 'local';
        });
        $key = $this->getCacheKey('');

        $template = '{{ cache_fragment }} {{ [1,2,3,4,5,6,7,8,9,10,11,21,31,14,15,16,17,18,19,20,30,21,45,157,14] | random }} {{ /cache_fragment }}';
        $output = (string) Antlers::parse($template);
        $this->assertStringContainsString('FragmentCache: `key` is required.', $output);
        $this->assertFalse(Cache::has($key));

        app()->detectEnvironment(function () {
            return 'production';
        });

        $output2 = (string) Antlers::parse($template);
        // in production we don't write this but parse the template directly
        $this->assertStringNotContainsString('FragmentCache: `key` is required.', $output2);
    }

    /** @test */
    public function test_it_handles_nested_cache_fragments()
    {
        // ARRANGE
        $template = '
            {{ cache_fragment key="outer" }}
                OUTER_BEFORE
                
                {{ cache_fragment key="inner" }}
                    INNER_CONTENT
                {{ /cache_fragment }}
                OUTER_AFTER
            {{ /cache_fragment }}
        ';

        $outerKey = $this->getCacheKey('outer');
        $innerKey = $this->getCacheKey('inner');


        $output = (string) Antlers::parse($template);

        $this->assertStringContainsString('OUTER_BEFORE', $output);
        $this->assertStringContainsString('INNER_CONTENT', $output);
        $this->assertStringContainsString('OUTER_AFTER', $output);

        // Check that both cache keys were created.
        $this->assertTrue(Cache::has($outerKey));
        $this->assertTrue(Cache::has($innerKey));

        // Check that the inner cache contains only its content.
        $innerCache = Cache::get($innerKey);
        $this->assertEquals('INNER_CONTENT', trim($innerCache['content']));

        // Check that the outer cache contains the *rendered content* of the inner cache.
        $outerCache = Cache::get($outerKey);
        $this->assertStringContainsString('INNER_CONTENT', $outerCache['content']);
        $this->assertStringNotContainsString('cache_fragment', $outerCache['content']);
    }

    public function test_it_watches_variables_when_auto_watch_is_enabled_on_fragment()
    {
        config([
            'statamic.fragment-cache.auto_watch_variables' => ['related_articles'],
            'statamic.fragment-cache.invalidation.enabled' => true,
        ]);
        $mainEntry = $this->makeStatamicEntry();
        // entry that will be the "dependency" or used in main entry (i.e featured article/related entries)
        $watchedEntry = $this->makeStatamicEntry();

        $context = $mainEntry->toAugmentedArray();
        $context['related_articles'] = [$watchedEntry];

        $template = '{{ cache_fragment key="watched-block" watch="true" }}{{ [1,3,24,654,786,1,321,56,11,12,14,15,161,17,75,32,678,6543,876] | random }}{{ /cache_fragment }}';

        $cacheKey = $this->getCacheKey('watched-block');
        $dependencyIndexKey = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $watchedEntry->id();

        (string) Antlers::parse($template, $context);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey));

        $watchedEntry->set('title', 'An Updated Watched Entry')->save();

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($dependencyIndexKey));
    }

    /** @test */
    public function test_it_generates_cache_key_with_cacheable_get_params()
    {
        // Simulate a request with query parameters
        $this->get('/?param1=value1&param2=value2&param3=value3');

        $key = $this->getCacheKey('test-key-get-params');
        $template = '{{ cache_fragment key="test-key-get-params" cacheable_get_params="param1,param3" }} {{ [1,2,3,4,5,6,7,8,9,10,11,21,31,14,15,16,17,18,19,20,30,21,45,157,14] | random }} {{ /cache_fragment }}';
        $output1 = (string) Antlers::parse($template);

        $expectedCacheKey = $key.'?param1=value1&param3=value3';
        $this->assertTrue(Cache::has($expectedCacheKey));

        $cachedData = Cache::get($expectedCacheKey)['content'];
        $this->assertEquals($output1, $cachedData);

        // Change a non-cacheable param, output should be same (served from cache)
        $this->get('/?param1=value1&param2=newValue&param3=value3');
        $output2 = (string) Antlers::parse($template);
        $this->assertEquals($output1, $output2);

        // Change a cacheable param, output should be different (new cache entry)
        $this->get('/?param1=newValue1&param2=value2&param3=value3');
        $output3 = (string) Antlers::parse($template);
        $this->assertNotEquals($output1, $output3);

        $expectedCacheKeyNew = $key.'?param1=newValue1&param3=value3';
        $this->assertTrue(Cache::has($expectedCacheKeyNew));
    }
}
