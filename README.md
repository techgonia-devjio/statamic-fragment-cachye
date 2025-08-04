# Statamic Fragment Cache

## Introduction
The Statamic Fragment Cache is caching addon for Statamic, engineered to enhance site performance through a granular, dependency-aware caching methodology.

### Core Concept
Conventional full-page caching strategies, while performant, often lack the flexibility required for dynamic websites. When a piece of content is updated, it can be difficult and inefficient to determine which cached pages have become stale, frequently leading to serving outdated information to users.

This package addresses that challenge by implementing a granular caching system. It provides a set of Antlers tags that allow developers to wrap discrete sections of a template—such as modules/block(in page builder context) or partials—in a cache block. These blocks are dependency-aware, meaning they maintain a record of the content entries they rely upon for rendering.

Consequently, when a source entry is modified, the addon can target and invalidate only the dependent cached fragments. This approach combines the performance benefits of caching with the data integrity of a fully dynamic site.


## How to Install

####  via composer as package
You can install this addon via Composer:

``` bash
composer require Devjio/statamic-fragment-cache
```

#### as addon directory

Place the addon directory into addons/devjio/statamic-fragment-cache.

Add the addon as a "path" repository to your project's composer.json file:
```json
{
    ...
  "repositories": [
    {
        "type": "path",
        "url": "addons/devjio/statamic-fragment-cache"
    }
  ],
  "require": {
     ....,
    "devjio/statamic-fragment-cache": "@dev"
  }

```

Execute `composer require devjio/statamic-fragment-cache` in your terminal.

#### as git submodule
 TODO: ....


Optionally, publish the configuration file to config/statamic/fragment-cache.php to override default settings:

php artisan vendor:publish --tag=fragment-cache-config

## Key Features

- **Dependency-Aware Invalidation**: The system listens for entry-saving events and purges only the cached fragments that are dependent on the modified entry.
- **Fragment Exclusion ("Donut Caching")**: A provided {{ ignore_cache }} tag allows developers to exclude dynamic sections, such as navigation menus or user-specific blocks, from within a larger cached fragment.
- **Optimized Live Preview**: The addon ensures an efficient and accurate editing experience. Unchanged modules on a page are served from a temporary cache for speed, while the module being actively edited updates in real-time.
- **Zero-Dependency Architecture**: The package is designed to function seamlessly with Statamic's file-based architecture and does not require external services such as Redis, a database, or other server software.
- **Extensive Configuration**: A configuration file provides control over all aspects of the addon, including cache durations, key-naming conventions, logging, and invalidation rules.
- **Logging**: Optional logging features are included to provide detailed insight into cache hits, misses, and content generation times, which is valuable for debugging and performance analysis.



## Usage
There are three types of cache tags:
 - CacheModule
 - CacheFragment
 - IgnoreCache

#### Caching a Module

The primary use case involves caching individual modules within a page builder context. To do this, wrap the module's partial render in the {{ cache_module }} tag and provide the parent entry's ID.
```antlers
{{# It is recommended to store the entry's ID before the loop #}}
{{ _entry_id = id }}

{{ modules }}
{{# The tag automatically generates a unique key from the module and parent context #}}
{{ cache_module entry_id="{_entry_id}" }}
{{ partial src="partials/modules/{type}" }}
{{ /cache_module }}
{{ /modules }}
 ```

#### Caching a Generic Fragment
For caching arbitrary template sections, such as a site header or footer, use the {{ cache_fragment }} tag. This tag requires a unique key parameter.

```antlers
{{# Example of caching the site footer #}}
{{ cache_fragment key="site_footer" }}
{{ partial:layout/footer }}
{{ /cache_fragment }}
```

### Dependency Watching (watch parameter)
The watch parameter instructs a cached fragment to be invalidated when a separate, specified entry is updated.

1. Automatic Watching (watch="true")

This mode tries to automatic dependency detection, depending on config. The tag will inspect the current context for an array of entries (as defined by the auto_watch_variables config key) and will automatically watch all entries found within it. This is ideal for parent pages that render lists of their children.

```antlers
{{# This module displays a list of child pages #}}

{{ modules }}
{{# The tag automatically generates a unique key from the module and parent context #}}
    {{ cache_module entry_id="{_entry_id}" watch="true" }}
        {{# The cache module will look for children prop(bcuz defined in config) for entries and watch them if they changed in future(on entry saved this will delete the exiting cache this entry and related) #}}
        {{ partial src="partials/modules/{type}" }}
    {{ /cache_module }}
{{ /modules }}
```

2. Manual Watching

For more complex relationships, such as a curated list of related entries, a pipe-separated string of entry IDs may be provided for explicit dependency tracking.

```antlers
{{# Manually construct a list of entry tags to watch #}}
{{ _watch_list = "" }}
{{ children }}
{{ _watch_list = "{_watch_list}|entry:{id}" }}
{{ /children }}

{{ cache_fragment key="related-articles" watch="{_watch_list}" }}
{{# ... render related articles content ... #}}
{{ /cache_fragment }}
```

3. Fragment Exclusion (ignore_cache)
To prevent a dynamic section within a larger cached block from being cached, wrap the dynamic content in {{ ignore_cache }} tags. This technique is particularly effective for components like navigation menus that must be rendered fresh on every request. I.e if you have language switch, it will cache the language dropdown links permanently the same page and will redirect on the same even if you are on the different page wants to change the language of that page.

```antlers
{{ cache_fragment key="site_header" }}
{{# This outer content is static and will be cached #}}
<header>
<a href="/" class="logo">...</a>
        {{# This inner block is dynamic and will be rendered on every request #}}
        {{ ignore_cache }}
            {{ partial:navigation/language_switch }}
        {{ /ignore_cache }}
    </header>
{{ /cache_fragment }}
```

### Configuration
The addon's settings can be configured by publishing the config file to config/statamic/fragment-cache.php.


| Key                                  | Description                                                                                            |
|--------------------------------------|:-------------------------------------------------------------------------------------------------------|
| enabled                              | A master switch to enable or disable the entire addon.                                                 |
| prefixes                             | An array of prefixes for cache keys to prevent naming collisions.                                      |
| default_duration                     | The default cache lifetime (e.g., '1 week'). A null value caches items indefinitely until invalidated. |
| auto_watch_variables                 | An array of variable names the tag will inspect when watch="true".                                     |
| invalidation.enabled                 | Enables or disables the automatic cache invalidation listener.                                         |
| invalidation.invalidate_static_cache | If true, the addon will also clear Statamic's static page cache (half/full strategies).                |
| live_preview.detect_using            | The method for detecting Live Preview ('header' or 'context').                                         |
| logging                              | Configuration for enabling, setting the channel, and defining the level for debug logging.             |


### Future Roadmap

The following enhancements are planned for future releases of the package:
- **Surgical Flushing**: Implement logic to invalidate only the specific modules that have actually changed within an entry, rather than all modules on that entry, for improved performance on content-heavy pages. 
- **Cache Warming Command**: Introduce an Artisan command (php please fragment-cache:warm) to proactively generate the cache for all site pages.("Brutal" way: create thread pool and call all sitemap urls)
- **Broader Invalidation**: Add event listeners for TermSaved, GlobalSetSaved, and other data types to provide more comprehensive cache invalidation.
- **Dashboard Widget**: Develop a Statamic control panel widget to display cache statistics, such as hit/miss ratios and cache sizes, single entry cache size, deletable, and more.
- **Database Support**: Currently, only file-based caching is supported. Need to test and implement database caching for larger sites with high traffic.
- **invalidate cache**
- 