<?php

namespace Kreatif\StatamicFragmentCache\Tests;

use Illuminate\Config\Repository;
use Kreatif\StatamicFragmentCache\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Kreatif\StatamicFragmentCache\Tests\Traits\CreatesEntries;


abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use CreatesEntries;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        // Add our temporary views path to Laravel's view finder.
        $paths = $app['config']->get('view.paths');
        array_unshift($paths, __DIR__.'/Fixtures/views');
        $app['config']->set('view.paths', $paths);
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        /** @var Repository $config */
        $config = ($app['config']);
        $config->set('statamic.editions.pro', true);
        $config->set('statamic.api.resources', [
            'collections' => true,
            'navs' => true,
            'taxonomies' => true,
            'assets' => true,
            'globals' => true,
            'forms' => true,
            'users' => true,
        ]);
    }
}
