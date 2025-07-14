<?php

namespace Kreatif\StatamicFragmentCache\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Kreatif\StatamicFragmentCache\Tests\TestCase;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;

class CacheModuleTagTest extends TestCase
{
    public function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
    public function getCacheKey(
        $key = 'test-key',
        ?string $entryId = null,
        ?string $type = null,
        ?string $moduleId = null,
        ?string $prefix = 'cache-module'
    )
    {
        $currentLocale = Site::current()->handle();
        $keysChunks = [];
        $keysChunks[] = $prefix;
        $keysChunks[] = $currentLocale;
        if (!empty($key)) $keysChunks[] = $key;
        if (!empty($entryId)) $keysChunks[] = $entryId;
        if (!empty($type)) $keysChunks[] = $type;
        if (!empty($moduleId)) $keysChunks[] = $moduleId;
        $key = implode(':', $keysChunks);
        return $key;
    }

    /** @test */
    public function test_it_caches_module_content()
    {
        config(['statamic.fragment-cache.default_duration' => '5 seconds']);

        $modules = [
            [
                'type' => 'text_block',
                'id' => Str::uuid()->toString(),
                'text' => 'Hello from the first module.',
            ],
            [
                'type' => 'cta_block',
                'id' => Str::uuid()->toString(),
                'button_text' => 'Call me.',
                'button_link' => '/call-me',
            ],
        ];

        $entry = $this->makeStatamicEntry(['modules' => $modules]);
        $entryContext = $entry->toAugmentedArray();
        $firstModule = $modules[0];
        $secondModule = $modules[1];

        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $entry->id() . '" }}
                    {{ partial src="partials/{type}" }}
                {{ /cache_module }}
            {{ /modules }}
        ';

        // Construct the expected cache keys for BOTH modules
        $expectedCacheKey1 = $this->getCacheKey($entry->id(), $firstModule['type'], $firstModule['id']);
        $expectedCacheKey2 = $this->getCacheKey($entry->id(), $secondModule['type'], $secondModule['id']);

        $this->assertFalse(Cache::has($expectedCacheKey1));
        $this->assertFalse(Cache::has($expectedCacheKey2));

        $output1 = (string) Antlers::parse($template, $entryContext);

        $this->assertTrue(Cache::has($expectedCacheKey1));
        $this->assertTrue(Cache::has($expectedCacheKey2));

        $expectedContentForModule1 = "<div>TextBlock Partial: {$firstModule['text']}</div>";
        $cachedData1 = Cache::get($expectedCacheKey1)['content'];
        $this->assertEquals(trim($expectedContentForModule1), trim($cachedData1));

        $cachedData2 = Cache::get($expectedCacheKey2)['content'];
        $expectedContentForModule2 = "<div>CTA Partial: {$secondModule['button_text']} - {$secondModule['button_link']}</div>";
        $this->assertEquals(trim($expectedContentForModule2), trim($cachedData2));

        $this->assertStringContainsString($firstModule['text'], $output1);
        $this->assertStringContainsString($secondModule['button_text'], $output1);

        $output2 = (string) Antlers::parse($template, $entryContext);

        $this->assertEquals(trim($output1), trim($output2));
    }

    public function test_it_should_not_caches_module_content_with_duration_null()
    {
        config(['statamic.fragment-cache.default_duration' => null]);
        $modules = [
            [
                'type' => 'text_block',
                'id' => Str::uuid()->toString(),
                'text' => 'Hello from the first module.',
            ],
            [
                'type' => 'cta_block',
                'id' => Str::uuid()->toString(),
                'button_text' => 'Call me.',
                'button_link' => '/call-me',
            ],
        ];

        $entry = $this->makeStatamicEntry(['modules' => $modules]);
        $entryContext = $entry->toAugmentedArray();
        $firstModule = $modules[0];
        $secondModule = $modules[1];
        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $entry->id() . '" }}
                    {{ partial src="partials/{type}" }}
                {{ /cache_module }}
            {{ /modules }}
        ';

        // Construct the expected cache keys for BOTH modules
        $expectedCacheKey1 = $this->getCacheKey($entry->id(), $firstModule['type'], $firstModule['id']);
        $expectedCacheKey2 = $this->getCacheKey($entry->id(), $secondModule['type'], $secondModule['id']);

        $this->assertFalse(Cache::has($expectedCacheKey1));
        $this->assertFalse(Cache::has($expectedCacheKey2));

        $output1 = (string) Antlers::parse($template, $entryContext);

        $this->assertTrue(Cache::has($expectedCacheKey1));
        $this->assertTrue(Cache::has($expectedCacheKey2));

        $expectedContentForModule1 = "<div>TextBlock Partial: {$firstModule['text']}</div>";
        $cachedData1 = Cache::get($expectedCacheKey1)['content'];
        $this->assertEquals(trim($expectedContentForModule1), trim($cachedData1));

        $expectedContentForModule2 = "<div>CTA Partial: {$secondModule['button_text']} - {$secondModule['button_link']}</div>";
        $cachedData2 = Cache::get($expectedCacheKey2)['content'];
        $this->assertEquals(trim($expectedContentForModule2), trim($cachedData2));

        $this->assertStringContainsString($firstModule['text'], $output1);
        $this->assertStringContainsString($secondModule['button_text'], $output1);

        $output2 = (string) Antlers::parse($template, $entryContext);

        $this->assertEquals(trim($output1), trim($output2));
    }


    public function test_it_invalidates_cache_on_entry_save()
    {
        config(['statamic.fragment-cache.invalidation.enabled' => true]);

        $entry = $this->makeStatamicEntry();
        $entryContext = $entry->toAugmentedArray();
        $firstModule = $entry->get('modules')[0];
        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $entry->id() . '" }}
                    {{ partial src="partials/{type}" }}
                {{ /cache_module }}
            {{ /modules }}
        ';
        $expectedCacheKey = $this->getCacheKey($entry->id(), $firstModule['type'], $firstModule['id']);
        $dependencyIndexKey = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry->id();

        $output1 = (string) Antlers::parse($template, $entryContext);
        $this->assertTrue(Cache::has($expectedCacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
        $this->assertContains($expectedCacheKey, Cache::get($dependencyIndexKey));

        // 2. ACT: Update and save the entry, which triggers the FlushEntryCache listener.
        $entry->set('title', 'An Updated Title')->save();

        // 3. ASSERT: The cache keys should now be gone.
        $this->assertFalse(Cache::has($expectedCacheKey));
        $this->assertFalse(Cache::has($dependencyIndexKey));
    }

    public function test_it_does_not_invalidates_cache_on_entry_save()
    {
        config(['statamic.fragment-cache.invalidation.enabled' => false]);

        $entry = $this->makeStatamicEntry();
        $entryContext = $entry->toAugmentedArray();
        $firstModule = $entry->get('modules')[0];
        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $entry->id() . '" }}
                    {{ partial src="partials/{type}" }}
                {{ /cache_module }}
            {{ /modules }}
        ';
        $expectedCacheKey = $this->getCacheKey($entry->id(), $firstModule['type'], $firstModule['id']);
        $dependencyIndexKey = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry->id();

        $output1 = (string) Antlers::parse($template, $entryContext);
        $this->assertTrue(Cache::has($expectedCacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
        $this->assertContains($expectedCacheKey, Cache::get($dependencyIndexKey));

        // 2. ACT: Update and save the entry, which triggers the FlushEntryCache listener.
        $entry->set('title', 'An Updated Title')->save();
        $this->assertTrue(Cache::has($expectedCacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
    }

    /** @test */
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

        $template = '{{ cache_fragment key="watched-block" watch="true" }}{{ [1,3,24,654,786,1,321,56] | random }}{{ /cache_fragment }}';

        $cacheKey = $this->getCacheKey(key: 'watched-block', prefix: 'cache-fragment');
        $dependencyIndexKey = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $watchedEntry->id();

        (string) Antlers::parse($template, $context);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey));

        $watchedEntry->set('title', 'An Updated Watched Entry')->save();

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($dependencyIndexKey));
    }

    public function test_it_watches_child_entries_when_auto_watch_is_enabled_on_module()
    {
        config([
            'statamic.fragment-cache.auto_watch_variables' => ['children'],
            'statamic.fragment-cache.invalidation.enabled' => true,
        ]);
        $parentEntry = $this->makeStatamicEntry();
        $childEntry = $this->makeStatamicEntry();
        $parentEntry->set('children', [$childEntry]);
        $parentEntry->save();

        $parentContext = $parentEntry->toAugmentedArray();
        $moduleContext = $parentEntry->get('modules')[0];

        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $parentEntry->id() . '" watch="true" }}
                    <span>{{ type }}</span>
                {{ /cache_module }}
            {{ /modules }}
        ';

        $cacheKey = $this->getCacheKey($parentEntry->id(), $moduleContext['type'], $moduleContext['id']);
        $dependencyIndexKey = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $childEntry->id();
        (string) Antlers::parse($template, $parentContext);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey));

        $childEntry->set('title', 'Updated Child Title')->save();

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($dependencyIndexKey));
    }

    /** @test */
    public function test_it_invalidates_with_simple_strategy_leaving_stale_dependencies()
    {
        config([
            'statamic.fragment-cache.invalidation.enabled' => true,
            'statamic.fragment-cache.invalidation.cleanup_strategy' => 'simple',
        ]);

        $hostEntry = $this->makeStatamicEntry();
        $moduleContext = $hostEntry->get('modules')[0];
        $entry1 = $this->makeStatamicEntry();
        $entry2 = $this->makeStatamicEntry();
        // Construct the watch string manually
        $watchString = "entry:{$entry1->id()}|entry:{$entry2->id()}";
        $template = '{{ cache_module entry_id="' . $hostEntry->id() . '" watch="' . $watchString . '" }}
            {{ [1,2,3,4,5,6,7,8,9,10,11,12,13,44,20,15,16,48,25,4,556,4,87,6513,546] | random }}
        {{ /cache_module }}';
        // The cache key is now for a module, not a fragment
        $cacheKey = $this->getCacheKey($hostEntry->id(), $moduleContext['type'], $moduleContext['id']);
        $dependencyIndexKey1 = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry1->id();
        $dependencyIndexKey2 = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry2->id();

        // 2. ACT
        // We parse with the module's context, as if we were inside the {{ modules }} loop
        (string) Antlers::parse($template, $moduleContext);

        // 3. ASSERT
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey1));
        $this->assertTrue(Cache::has($dependencyIndexKey2));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey1));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey2));

        // 4. ACT 2: Update one of the watched entries
        $entry2->set('title', 'An Updated Watched Entry')->save();

        // 5. ASSERT 2: The cache for the module should be gone, along with its dependency indexes
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey1));
        $this->assertFalse(Cache::has($dependencyIndexKey2));
    }

    /** @test */
    public function test_it_invalidates_with_full_strategy_removing_all_dependencies()
    {
        config([
            'statamic.fragment-cache.invalidation.enabled' => true,
            'statamic.fragment-cache.invalidation.cleanup_strategy' => 'full',
        ]);

        $hostEntry = $this->makeStatamicEntry();
        $moduleContext = $hostEntry->get('modules')[0];
        $entry1 = $this->makeStatamicEntry();
        $entry2 = $this->makeStatamicEntry();
        // Construct the watch string manually
        $watchString = "entry:{$entry1->id()}|entry:{$entry2->id()}";
        $template = '{{ cache_module entry_id="' . $hostEntry->id() . '" watch="' . $watchString . '" }}
            {{ [1,2,3,4,5,6,7,8,9,10,11,12,13,44,20,15,16,48,25,4,556,4,87,6513,546] | random }}
        {{ /cache_module }}';
        // The cache key is now for a module, not a fragment
        $cacheKey = $this->getCacheKey($hostEntry->id(), $moduleContext['type'], $moduleContext['id']);
        $dependencyIndexKey1 = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry1->id();
        $dependencyIndexKey2 = config('statamic.fragment-cache.prefixes.dependency_index') . ':entry:' . $entry2->id();

        // 2. ACT
        // We parse with the module's context, as if we were inside the {{ modules }} loop
        (string) Antlers::parse($template, $moduleContext);

        // 3. ASSERT
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($dependencyIndexKey1));
        $this->assertTrue(Cache::has($dependencyIndexKey2));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey1));
        $this->assertContains($cacheKey, Cache::get($dependencyIndexKey2));

        // 4. ACT 2: Update one of the watched entries
        $entry2->set('title', 'An Updated Watched Entry')->save();

        // 5. ASSERT 2: The cache for the module should be gone, along with its dependency indexes
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($dependencyIndexKey2));
        $this->assertFalse(Cache::has($dependencyIndexKey1));
    }


    /**
     * @test
     *
     * lack of knowledge to test live preview
     */
    public function test_it_handles_live_preview_mode_with_context_detection()
    {
        // TODO: see in future
        config(['statamic.fragment-cache.live_preview.detect_using' => 'context']);
        $entry = $this->makeStatamicEntry();

        $template = '{{ cache_module entry_id="' . $entry->id() . '" }}
            {{ title }}
        {{ /cache_module }}';

        $context = $entry->toAugmentedArray();
        $livePreviewContext = array_merge($context, [
            'live_preview' => true,
        ]);

        $output1 = (string) Antlers::parse($template, $livePreviewContext);
        $this->assertNotEmpty($output1);

        $livePreviewContext['title'] = 'Updated live preview content';

        // Second render should produce different output because the hash will change
        $output2 = (string) Antlers::parse($template, $livePreviewContext);
        $this->assertNotEmpty($output2);
        $this->assertNotEquals($output1, $output2);
    }

    /**
     * @test
     *
     * lack of knowledge to test live preview or simply running out of budget
     */
    public function test_it_handles_live_preview_mode_with_header_detection()
    {
        // TODO: see in future
        config(['statamic.fragment-cache.live_preview.detect_using' => 'header']);
        $entry = $this->makeStatamicEntry();
        $template = '{{ cache_module entry_id="' . $entry->id() . '" }}
            {{ title }}
        {{ /cache_module }}';
        $moduleContext = $entry->get('modules')[0];

        /*$output1 = (string) $this->withHeaders(['Statamic-Live-Preview' => 'true'])
            ->....($template, $moduleContext);
        $this->assertNotEmpty($output1);
        */

    }

    /** @test */
    public function test_it_generates_cache_key_with_all_module_parameters()
    {
        $entry = $this->makeStatamicEntry();
        $moduleContext = $entry->get('modules')[0];
        $template = '{{ cache_module entry_id="' . $entry->id() . '" }}{{ title }}{{ /cache_module }}';

        // 2. ACT
        (string) Antlers::parse($template, $moduleContext);

        // 3. ASSERT
        // Manually build the exact key we expect the tag to generate
        $expectedCacheKey = $this->getCacheKey(
            $entry->id(),
            $moduleContext['type'],
            $moduleContext['id']
        );

        // Assert that the cache contains an item with this specific, correctly formatted key.
        $this->assertTrue(Cache::has($expectedCacheKey));
    }
}
