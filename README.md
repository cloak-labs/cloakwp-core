# CloakWP Core

OOP wrappers around WordPress core, providing you with a beautiful, modern developer experience. Same primitives you already know — post types, taxonomies, nav menus, `wp_enqueue_*`, REST, Gutenberg — with fluent builders, explicit registration, and hook timing handled for you.

It is a library, not a plugin: Composer autoloads `CloakWP\Core\`, and nothing attaches to WordPress until you call `register()` / `boot()`.

```bash
composer require cloakwp/core
```

PHP 8.2+.

## Why it exists

WordPress APIs work, but they are procedural, easy to get wrong, inconsistent, lack a self-documenting nature, frankly ugly to look at, mostly built in a different era with fragmented design decisions, locked by the need to maintain backwards-compatibility... the list goes on.

**This package aims to make core WordPress APIs feel like the core Laravel team rebuilt it from scratch.** Whether you're building a brochure site or a WordPress plugin, you'll love building on top of WordPress again.

Some core tenets:

- **Say what you mean** — `ContentType::make('project')->showInRest()->supports(['title', 'editor'])` instead of a `$args` array you copy between sites.
- **Register at the right time** — REST routes wait for `rest_api_init`; post types and taxonomies wait for `init`; assets enqueue on the hooks you name.
- **Make the dangerous choices explicit** — a REST route is not public unless you mark it `public()` or pass a `permission()` callback.
- **Keep side effects opt-in** — requiring the package does not add admin UI, cron, or filters. Features implement a small `register()` contract and you boot the ones you want.

We still have plenty of abstractions to build, but for now here's what is available:

## Content modeling

Stop defining your content modeling (CPTs, taxonomies, etc.) via a settings UI that saves config to the database. It should be defined in code. The following classes make that easy-breezy-beautiful:

`ContentType` and `Taxonomy` wrap `register_post_type()` / `register_taxonomy()`. Subclass and implement `configure()`, or chain `::make('slug')`.

`ContentModel` is the registry. Collect types, optionally extend them before WordPress sees them, then `boot()`.

```php
use CloakWP\Core\Content\ContentModel;
use CloakWP\Core\Content\ContentType;
use CloakWP\Core\Content\Taxonomy;

$model = ContentModel::getInstance();

$model->registerTypes([
  ContentType::make('project')
    ->public(true)
    ->showInRest()
    ->supports(['title', 'editor', 'thumbnail'])
    ->rewrite(['slug' => 'work']),
]);

$model->registerTaxonomies([
  Taxonomy::make('discipline')
    ->public(true)
    ->hierarchical(true)
    ->showInRest(true)
    ->forTypes(['project']),
]);

// optionally modify somewhere further down the chain, before boot:
$model->extendTypes(function (ContentType $type) {
  if ($type->getSlug() === 'project') {
    $type->menuIcon('dashicons-portfolio');
  }
});

$model->boot();
```

On multisite, `ContentModel::getInstance()` is per site; use `forSite($id)` when you need a specific site’s registry.

`MenuLocation` is `register_nav_menu()` with a slug and label:

```php
use CloakWP\Core\Content\MenuLocation;

MenuLocation::make('header', 'Header')->register();
```

## Assets

`Script` and `Stylesheet` wrap `wp_enqueue_script` / `wp_enqueue_style`: handle, src, deps, version, hook, priority, `adminOnly()`, and script loading strategy (`defer` / `async`). Enqueue a list with `Assets::enqueue()`.

```php
use CloakWP\Core\Enqueue\{Assets, Script, Stylesheet};

Assets::enqueue([
  Stylesheet::make('theme-editor')
    ->hooks(['enqueue_block_assets'])
    ->adminOnly()
    ->src(get_theme_file_uri('/assets/css/editor.css')),
  Script::make('theme-editor')
    ->hooks(['enqueue_block_assets'])
    ->adminOnly()
    ->loadingStrategy('defer')
    ->src(get_theme_file_uri('/assets/js/editor.js')),
]);
```

## REST Endpoints

`RestApi` registers a namespace; `Route` is one path. Registration is deferred to `rest_api_init`. Every route needs an explicit permission: `public()` or `permission()`.

```php
use CloakWP\Core\Rest\RestApi;
use CloakWP\Core\Rest\Route;

RestApi::make('my-theme/v1')
  ->routes([
    Route::get('/projects', function (\WP_REST_Request $request) {
      return rest_ensure_response([]);
    })
      ->public()
      ->args([
        'discipline' => ['required' => false, 'type' => 'string'],
      ]),
    Route::post('/projects', function (\WP_REST_Request $request) {
      return rest_ensure_response([]);
    })
      ->permission(fn () => current_user_can('edit_posts')),
  ])
  ->register();
```

`Route::get/post/put/patch/delete` cover the usual methods; `Route::make('PROPFIND', ...)` or a method array covers the rest. Closures, callable arrays, and invokable objects are all valid handlers.

## Gutenberg

`AllowedBlocks` filters `allowed_block_types_all`. You list the core blocks you want; it intersects with whatever earlier filters already allowed or denied, and leaves non-core blocks alone so plugins and custom blocks keep working. `core/block` stays available for patterns unless something upstream removed it.

```php
use CloakWP\Core\Gutenberg\AllowedBlocks;
use CloakWP\Core\Gutenberg\BlockEditor;

AllowedBlocks::make([
  'core/paragraph',
  'core/heading' => ['postTypes' => ['page']],
])->register();

if (BlockEditor::isActive()) {
  // editor-screen-only wiring — call on `init` priority 4 or later
}
```

`BlockEditor::isActive()` is true on block-editor admin screens after post types exist.

## Media library

`LibraryFilter` is a first-class Media Library dropdown (list view + grid/modal) instead of hand-rolled `restrict_manage_posts` / `ajax_query_attachments_args` glue. Boot the shared toolbar once, then register filters:

```php
use CloakWP\Core\Features\MediaLibraryFilters;
use CloakWP\Core\Media\LibraryFilter;

MediaLibraryFilters::make()->register();

LibraryFilter::make('orientation')
  ->label('Filter by orientation')
  ->allLabel('All orientations')
  ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
  ->metaKey('_media_orientation')
  ->register();
```

## Theme autoloader

`ThemeAutoloader::register()` loads classes under the `Theme\...` namespace from the child theme when the class file exists, otherwise from the parent theme (useful for enabling child-theme overrides). Use `ParentTheme\...` to force loading from the parent theme.

## Features

Anything that attaches WordPress hooks implements `CloakWP\Core\Features\Feature` and only runs when you call `register()`. `MediaLibraryFilters` is the bundled example: compose it from your theme the same way you would any other module.

## Utils

Small helpers for recurring WordPress chores: debug logging (`CLOAKWP_DEBUG`), coerce IDs/arrays to `WP_Post`, draft-safe permalink pathnames, author formatting, post-type inventories, and requiring PHP files from a theme directory.
