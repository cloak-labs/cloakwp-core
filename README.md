# CloakWP Core

A set of OOP abstractions around WordPress core to improve developer experience.

## Media library filters

`CloakWP\Core\Media\LibraryFilter` is the shared primitive used by CloakWP media plugins (Media Categories, Media Orientation, …). One `register()` call wires:

- List view dropdown (`restrict_manage_posts` on the media filter bar)
- List query (`pre_get_posts` on `upload.php`)
- Grid / modal / ACF picker query (`ajax_query_attachments_args`)
- Grid / modal toolbar `<select>` via `wp.media.view.AttachmentFilters`

```php
use CloakWP\Core\Media\LibraryFilter;

LibraryFilter::make('orientation')
  ->label('Filter by orientation')
  ->allLabel('All orientations')
  ->options([
    'portrait' => 'Portrait',
    'landscape' => 'Landscape',
    'square' => 'Square',
  ])
  ->metaKey('_media_orientation') // or ->query(fn (array $args, string $value): array => …)
  ->priority(-76)                 // core type is -80, date is -75
  ->register();
```

Custom UIs (hierarchical multi-select, extra controls) use `->grid('custom')` plus `->listRenderer()` / `->query()`, and attach a view with `cloakwpMediaLibrary.onToolbar(function (browser) { … })` in JavaScript. Core moves every secondary toolbar control (except spinner / buttons / Clear) into the shared filter track — custom views do not need a special class.

A **Clear** control sits to the right of the filter selects on `upload.php` (list + grid) and in every media modal (Add Media, featured image, ACF Image/Gallery/File). It boots on `plugins_loaded` even when no custom filters are registered. One click resets:

- Core type and date (using each `AttachmentFilters` view’s own idle props — date is `year`/`monthnum: false`)
- Every `grid('select')` `LibraryFilter` (via `queryVar`)
- Custom filters that declare extra Backbone keys with `->modelKeys([…])` and reset their chrome with `cloakwpMediaLibrary.onClear(function (browser) { … })` (or the `cloakwp.mediaLibrary.clearFilters` jQuery event)

```php
LibraryFilter::make('color')
    ->queryVar('media_color')
    ->grid(LibraryFilter::GRID_CUSTOM)
    ->modelKeys(['media_color', 'color_tax']) // any keys the custom UI writes onto library.props
    ->query(fn (array $args, string $value): array => /* … */)
    ->register();
```

`queryVar` is always cleared. `modelKeys()` is for custom UIs whose model/URL key differs from `queryVar` (for example a taxonomy slug). Select filters do not need it.

If `cloakwp/media-library-state` is loaded, Clear also calls `cloakwpMediaLibraryState.clearFilters()` when that function exists — so `?type=` / `?year=` / custom filter params and `media_pages` are dropped from the grid URL. Core never requires that package.

The toolbar keeps type, date, and every CloakWP filter on one row; overflow scrolls horizontally instead of wrapping onto the attachments grid.

`CloakWP\Core\Media\QueryArgs::mergeTaxQuery()` / `mergeMetaQuery()` combine clauses onto an existing query without clobbering sibling filters.
