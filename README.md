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

Custom UIs (hierarchical multi-select, extra controls) use `->grid('custom')` plus `->listRenderer()` / `->query()`, and attach a view with `cloakwpMediaLibrary.onToolbar(function (browser) { … })` in JavaScript.

`CloakWP\Core\Media\QueryArgs::mergeTaxQuery()` / `mergeMetaQuery()` combine clauses onto an existing query without clobbering sibling filters.
