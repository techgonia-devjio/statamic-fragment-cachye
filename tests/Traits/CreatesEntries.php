<?php

namespace Devjio\StatamicFragmentCache\Tests\Traits;

use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;

trait CreatesEntries
{
    protected function makeStatamicEntry(
        array $data = [],
        string $collectionHandle = 'pages',
    ) {
        // Ensure the collection and blueprint exist before creating an entry.
        $this->makeCollectionAndBlueprint($collectionHandle);
        $entryData = array_merge([
            'title' => fake()->sentence(),
            'modules' => [
                [
                    'type' => 'text_block',
                    'id' => Str::uuid()->toString(),
                    'text' => 'Hello from the first module.',
                ],
                [
                    'type' => 'cta_block',
                    'id' => Str::uuid()->toString(),
                    'button_text' => 'Call me.',
                    'button_link' => '/call-me', // The value can be a string
                ],
            ],
        ], $data);
        $slug = Str::slug($entryData['title']);
        /** @var Entry $entry */
        $entry = Entry::make()
            ->collection($collectionHandle)
            ->slug($slug)
            ->data($entryData);
        $entry->save();
        return $entry;
    }

    protected function makeCollectionAndBlueprint(string $collectionHandle): void
    {
        // Only create if it doesn't exist to avoid errors on later test runs.
        if (Collection::findByHandle($collectionHandle)) {
            return;
        }

        $collection = Collection::make($collectionHandle)
            ->title(Str::title($collectionHandle))
            ->routes('/{slug}')
            ->save();

        // Define the fields for our test blueprint.
        $fields = [
            [
                'handle' => 'title',
                'field' => ['type' => 'text', 'display' => 'Title'],
            ],
            [
                'handle' => 'modules', // This is our page builder field
                'field' => [
                    'type' => 'replicator',
                    'display' => 'Page Builder',
                    'sets' => [
                        'text_block' => [
                            'display' => 'Text Block',
                            'fields' => [
                                ['handle' => 'text', 'field' => ['type' => 'textarea']],
                            ],
                        ],
                        'image_block' => [
                            'display' => 'Image Block',
                            'fields' => [
                                ['handle' => 'image', 'field' => ['type' => 'assets', 'max_files' => 1]],
                                ['handle' => 'caption', 'field' => ['type' => 'text', 'display' => 'Caption']],
                            ],
                        ],
                        'cta_block' => [
                            'display' => 'Call to Action',
                            'fields' => [
                                ['handle' => 'button_text', 'field' => ['type' => 'text']],
                                // **THE FIX:** Changed from 'url' to 'text'. The 'url' fieldtype is not
                                // always available in the minimal test environment, and using 'text'
                                // doesn't affect the logic of testing the caching functionality.
                                ['handle' => 'button_link', 'field' => ['type' => 'text']],
                            ],
                        ]
                    ],
                ],
            ],
        ];

        Blueprint::make()
            ->setHandle($collection->handle())
            ->setNamespace('collections.'.$collection->handle())
            ->setContents(['sections' => ['main' => ['fields' => $fields]]])
            ->save();
    }
}
