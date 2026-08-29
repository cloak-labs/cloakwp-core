# CloakWP Core

OOP wrappers around WordPress for CloakWP sites and sister packages. Fluent builders, a shared content registry, and opinionated admin/editor helpers — not a plugin, not a settings UI.

```bash
composer require cloakwp/core
```

PHP 8.2+. Autoloads as `CloakWP\Core\`.

## CMS

`CMS` is the usual entry point. It extends [Better WP API](https://github.com/snicco/better-wp-api) and adds CloakWP-specific wiring: enqueue assets, register a content model, allowlist Gutenberg core blocks (optionally per post type), and toggle common WP/Yoast/admin behavior.

```php
use CloakWP\Core\CMS;

CMS::getInstance()
  ->contentTypes([Project::class, Service::class])
  ->assets([$themeCss, $editorJs])
  ->enabledCoreBlocks(['core/paragraph', 'core/heading', 'core/image'])
  ->enableFeaturedImages()
  ->disableComments()
  ->deprioritizeYoastMetabox();
```

The rest of the fluent surface is the same idea: strip unused chrome (widgets, Customizer, dashboard clutter, update nags), tune Gutenberg (patterns, Openverse, font library, block-plugin upsells), relax or hide Yoast where it gets in the way, allow SVGs, let editors manage menus without the rest of Appearance, and a few local-only DX hooks (BrowserSync, Xdebug info).

`WpContext` is available as `CMS::$context` when you need to know if you’re in admin, REST, AJAX, etc.

## Content model

`ContentType` and `Taxonomy` are fluent replacements for `register_post_type()` / `register_taxonomy()`. Subclass and implement `configure()`, or chain `::make('slug')`. They can attach Extended ACF field groups, virtual fields, Gutenberg templates, and REST/editor settings.

`ContentModel` is the registry: register types, extend them before WordPress boots, then `registerWithWordPress()`. `CMS::contentTypes()` does that for you.

`MenuLocation` registers a nav-menu location and optional ACF fields on the menu or its items.

## Assets

`Script` and `Stylesheet` wrap `wp_enqueue_*` with handles, deps, hooks, priority, `adminOnly()`, and script loading strategy (`defer` / `async`). Pass them to `CMS::assets()` or call `enqueue()` yourself.

## Media library filters

`LibraryFilter` is the shared primitive used by CloakWP media plugins (Media Categories, orientation, …). One `register()` wires the list-view dropdown, the list query, ajax/grid/modal queries, and a toolbar `<select>`.

```php
use CloakWP\Core\Media\LibraryFilter;

LibraryFilter::make('orientation')
  ->label('Filter by orientation')
  ->allLabel('All orientations')
  ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
  ->metaKey('_media_orientation') // or ->query(fn (array $args, string $value): array => …)
  ->register();
```

Custom UIs use `->grid('custom')` plus `listRenderer()` / `query()`, then attach a view with `cloakwpMediaLibrary.onToolbar(...)`. Core collects type, date, and every filter onto one scrolling toolbar row and adds a **Clear** control (even when no custom filters exist). Select filters clear via their `queryVar`; custom UIs declare extra Backbone keys with `->modelKeys([...])` and reset their chrome with `cloakwpMediaLibrary.onClear(...)`.

If `cloakwp/media-library-state` is present, Clear also drops persisted filter params from the grid URL. Core does not require that package.

`QueryArgs::mergeTaxQuery()` / `mergeMetaQuery()` add a clause without clobbering siblings.

## Theme autoloader

`ThemeAutoloader::register()` loads `Theme\...` from the child theme when the class exists, otherwise from the parent. Use `ParentTheme\...` to force the parent.

## Utils

Small helpers used across CloakWP: debug logging (`CLOAKWP_DEBUG`), coerce IDs/arrays to `WP_Post`, draft-safe permalink pathnames, author formatting, post-type inventories, and similar one-off WP chores. Reach for a method when you need it — this isn’t a framework.
