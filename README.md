# CloakWP Core

OOP wrappers around WordPress for CloakWP sites and sister packages. Fluent builders, a shared content registry, and focused Gutenberg helpers — not a plugin, not a settings UI.

```bash
composer require cloakwp/core
```

PHP 8.2+. Autoloads as `CloakWP\Core\`.

## REST APIs

Routes require an explicit public or authenticated permission decision. Registration is deferred to `rest_api_init`.

```php
use CloakWP\Core\Rest\RestApi;
use CloakWP\Core\Rest\Route;

RestApi::make('cloakwp')
  ->routes([
    Route::get('/menus', new GetMenus())
      ->public()
      ->args([
        'location' => ['required' => true, 'type' => 'string'],
      ]),
    Route::post('/menus', new UpdateMenu())
      ->permission(fn () => current_user_can('edit_theme_options')),
  ])
  ->register();
```

Use `Route::make('PROPFIND', ...)` or pass a method array for arbitrary HTTP methods. Closures, callable arrays, and invokable handler objects are supported.

## Assets

`Script` and `Stylesheet` wrap `wp_enqueue_*` with handles, deps, hooks, priority, `adminOnly()`, and script loading strategy (`defer` / `async`). Enqueue a collection with `Assets::enqueue([...])`.

```php
use CloakWP\Core\Enqueue\{Assets, Script, Stylesheet};

Assets::enqueue([
  Stylesheet::make('theme-editor')
    ->hooks(['enqueue_block_assets'])
    ->adminOnly()
    ->src(get_theme_file_uri('/assets/css/editor.css')),
]);
```

## Gutenberg

```php
use CloakWP\Core\Gutenberg\AllowedBlocks;
use CloakWP\Core\Gutenberg\BlockEditor;

AllowedBlocks::make([
  'core/paragraph',
  'core/heading' => ['postTypes' => ['page']],
])->register();

if (BlockEditor::isActive()) {
  // editor-only wiring
}
```

When no earlier filter restricts blocks, non-core blocks remain available and `core/block` is included for patterns. Existing upstream allowlists and denials are always respected.

## Content model

`ContentType` and `Taxonomy` are fluent replacements for `register_post_type()` / `register_taxonomy()`. Subclass and implement `configure()`, or chain `::make('slug')`.

`ContentModel` is the registry: register types, extend them before WordPress boots, then `registerWithWordPress()`.

`MenuLocation` registers a nav-menu location and optional ACF fields on the menu or its items.

## Features

`CloakWP\Core\Features\Feature` is the contract for opt-in modules that register their own hooks when `register()` is called. Agency stacks compose features explicitly — Core no longer auto-applies admin opinions.

## Media library filters

`LibraryFilter` is the shared primitive used by CloakWP media plugins. Boot the shared Media Library toolbar behavior explicitly:

```php
use CloakWP\Core\Features\MediaLibraryFilters;

MediaLibraryFilters::make()->register();
```

Loading Composer's autoloader alone does not attach WordPress hooks.

## Theme autoloader

`ThemeAutoloader::register()` loads `Theme\...` from the child theme when the class exists, otherwise from the parent. Use `ParentTheme\...` to force the parent.

## Utils

Small helpers used across CloakWP: debug logging (`CLOAKWP_DEBUG`), coerce IDs/arrays to `WP_Post`, draft-safe permalink pathnames, author formatting, post-type inventories, and similar one-off WP chores.

## 2.0 breaking changes

- Removed the `CMS` god object and Better WP API inheritance.
- Admin/Yoast/DX toggles that previously lived on `CMS` moved to user-land (e.g. agency base theme `AgencyStack`).
- Use `Assets`, `AllowedBlocks`, `BlockEditor`, and `ContentModel` directly.
