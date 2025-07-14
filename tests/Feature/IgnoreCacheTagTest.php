<?php

namespace Kreatif\StatamicFragmentCache\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Kreatif\StatamicFragmentCache\Tests\TestCase;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;

class IgnoreCacheTagTest extends TestCase
{
    public function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function getCacheKey($key = 'test-key', $prefix = 'cache-fragment')
    {
        $currentLocale = Site::current()->handle();
        return "{$prefix}:{$currentLocale}:{$key}";
    }

    /** @test */
    public function test_it_does_not_cache_content_within_ignore_cache_tag()
    {
        $cacheKey = $this->getCacheKey('donut-hole-test');
        $template = '
            {{ cache_fragment key="donut-hole-test" }}
                CACHED_BEFORE
                {{ ignore_cache }}{{ dynamic_value }}{{ /ignore_cache }}
                CACHED_AFTER
            {{ /cache_fragment }}
        ';
        $output1 = (string) Antlers::parse($template, ['dynamic_value' => now()->format('U.u')]);
        usleep(1000*1000); // 1 second
        $output2 = (string) Antlers::parse($template, ['dynamic_value' => now()->format('U.u')]);

        $this->assertNotEquals($output1, $output2);

        $this->assertStringContainsString('CACHED_BEFORE', $output1);
        $this->assertStringContainsString('CACHED_AFTER', $output1);
        $this->assertStringContainsString('CACHED_BEFORE', $output2);
        $this->assertStringContainsString('CACHED_AFTER', $output2);

        $this->assertTrue(Cache::has($cacheKey));
        $cachedData = Cache::get($cacheKey)['content'];
        $this->assertStringContainsString('<!--IGNORE_CACHE_PLACEHOLDER_', $cachedData);
        $this->assertStringNotContainsString($cachedData, $output1);
    }

    /** @test */
    public function test_it_handles_multiple_ignore_cache_blocks()
    {
        $template = '
            {{ cache_fragment key="multiple-holes" }}
                Block1-{{ ignore_cache }}{{ val1 }}{{ /ignore_cache }}-Block2-{{ ignore_cache }}{{ val2 }}{{ /ignore_cache }}
            {{ /cache_fragment }}
        ';
        $output1 = (string) Antlers::parse($template, ['val1' => 'A', 'val2' => 'B']);
        $output2 = (string) Antlers::parse($template, ['val1' => 'C', 'val2' => 'D']);

        $this->assertEquals('Block1-A-Block2-B', trim($output1));
        $this->assertEquals('Block1-C-Block2-D', trim($output2));
        $this->assertNotEquals($output1, $output2);
    }

    /** @test */
    public function test_it_works_without_outer_cache_tag()
    {
        $template = 'This is {{ ignore_cache }}NOT CACHED{{ /ignore_cache }} content.';

        $output = (string) Antlers::parse($template);
        $this->assertEquals('This is NOT CACHED content.', $output);
    }

    /** @test */
    public function test_antlers_variables_within_ignore_cache_are_parsed()
    {
        $template = '
            {{ cache_fragment key="dynamic-hole" }}
                STATIC-{{ ignore_cache }}{{ my_var }}{{ /ignore_cache }}-STATIC
            {{ /cache_fragment }}
        ';

        $output1 = (string) Antlers::parse($template, ['my_var' => 'FIRST_RUN']);
        $this->assertEquals('STATIC-FIRST_RUN-STATIC', trim($output1));
        // Second run with a different variable
        $output2 = (string) Antlers::parse($template, ['my_var' => 'SECOND_RUN']);
        $this->assertEquals('STATIC-SECOND_RUN-STATIC', trim($output2));
        $this->assertNotEquals($output1, $output2);
    }


    /** @test */
    public function test_it_ignores_cache_within_a_cached_module()
    {
        $entry = $this->makeStatamicEntry();
        $module = $entry->get('modules')[0];

        $template = '
            {{ modules }}
                {{ cache_module entry_id="' . $entry->id() . '" }}
                    <div>
                        <span>CACHED: {{ text }}</span>
                        <span>UNCACHED: {{ ignore_cache }}{{ dynamic_value }}{{ /ignore_cache }}</span>
                    </div>
                {{ /cache_module }}
            {{ /modules }}
        ';

        $output1 = (string) Antlers::parse($template, array_merge($entry->toAugmentedArray(), ['dynamic_value' => 'Run 1']));
        $output2 = (string) Antlers::parse($template, array_merge($entry->toAugmentedArray(), ['dynamic_value' => 'Run 2']));


        $this->assertNotEquals($output1, $output2);
        $this->assertStringContainsString("CACHED: {$module['text']}", $output1);
        $this->assertStringContainsString("UNCACHED: Run 1", $output1);
        $this->assertStringContainsString("CACHED: {$module['text']}", $output2);
        $this->assertStringContainsString("UNCACHED: Run 2", $output2);
    }

    public function test_nested_cache_tags_with_ignore_cache_in_the_middle()
    {
        $template = '
            {{ cache_fragment key="outer" }}
                OUTER_BEFORE
                {{ cache_fragment key="inner" }}
                    INNER_BEFORE
                    {{ ignore_cache }}{{ dynamic_value }}{{ /ignore_cache }}
                    INNER_AFTER
                {{ /cache_fragment }}
                OUTER_AFTER
            {{ /cache_fragment }}
        ';

        $output1 = (string) Antlers::parse($template, ['dynamic_value' => 'A']);
        $output2 = (string) Antlers::parse($template, ['dynamic_value' => 'B']);

        // ASSERT
        $this->assertNotEquals($output1, $output2);
        $this->assertStringContainsString('OUTER_BEFORE', $output1);
        $this->assertStringContainsString('INNER_BEFORE', $output1);
        $this->assertStringContainsString('A', $output1);
        $this->assertStringContainsString('OUTER_BEFORE', $output2);
        $this->assertStringContainsString('INNER_BEFORE', $output2);
        $this->assertStringContainsString('B', $output2);

        // Check that both cache keys were created.
        $this->assertTrue(Cache::has($this->getCacheKey('outer')));
        $this->assertTrue(Cache::has($this->getCacheKey('inner')));
    }

    public function test_ignore_cache_is_case_insensitive()
    {
        $template = '
            {{ cache_fragment key="case-insensitive-test" }}
                CACHED-{{ IGNORE_CACHE }}{{ dynamic_value }}{{ /IGNORE_CACHE }}-CACHED
            {{ /cache_fragment }}
        ';

        $output1 = (string) Antlers::parse($template, ['dynamic_value' => 'A']);
        $output2 = (string) Antlers::parse($template, ['dynamic_value' => 'B']);

        $this->assertNotEquals($output1, $output2);
        $this->assertEquals('CACHED-A-CACHED', trim($output1));
        $this->assertEquals('CACHED-B-CACHED', trim($output2));
    }

    /** @test */
    public function test_ignore_cache_with_no_content_renders_nothing()
    {
        // ARRANGE
        // The ignore_cache block is now empty, matching the test's intent.
        $template = '{{ cache_fragment key="empty-hole" }}BEFORE{{ ignore_cache }}{{ /ignore_cache }}AFTER{{ /cache_fragment }}';

        // ACT
        $output = (string) Antlers::parse($template);
        $cachedData = Cache::get($this->getCacheKey('empty-hole'))['content'];
        $this->assertEquals('BEFOREAFTER', trim($output));
        $this->assertStringContainsString('<!--IGNORE_CACHE_PLACEHOLDER_', $cachedData);
    }
}
