<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Media\LibraryFilter;
use CloakWP\Core\Media\LibraryFilters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WP_Query;

final class LibraryFilterTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
    LibraryFilters::reset();
  }

  public function testMetaKeyBuildsMetaQuery(): void
  {
    $filter = LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation');

    $args = $filter->applyToArgs(['post_type' => 'attachment'], 'portrait');

    $this->assertSame([
      [
        'key' => '_media_orientation',
        'value' => 'portrait',
        'compare' => '=',
      ],
    ], $args['meta_query']);
  }

  public function testEmptyValueLeavesArgsUntouched(): void
  {
    $filter = LibraryFilter::make('orientation')->metaKey('_media_orientation');
    $args = ['post_type' => 'attachment'];

    $this->assertSame($args, $filter->applyToArgs($args, ''));
  }

  public function testUnknownOptionDoesNotAddMetaQuery(): void
  {
    $filter = LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation');
    $args = ['post_type' => 'attachment'];

    $this->assertSame($args, $filter->applyToArgs($args, 'nope'));
  }

  public function testCustomQueryCallback(): void
  {
    $filter = LibraryFilter::make('media_category')
      ->query(static function (array $args, string $value): array {
        $args['tax_query'] = [['taxonomy' => 'category_media', 'terms' => [(int) $value]]];

        return $args;
      });

    $args = $filter->applyToArgs([], '12');

    $this->assertSame(12, $args['tax_query'][0]['terms'][0]);
  }

  public function testRenderListFilterOutputsSelect(): void
  {
    $_REQUEST['orientation'] = 'landscape';

    $filter = LibraryFilter::make('orientation')
      ->label('Filter by orientation')
      ->allLabel('All orientations')
      ->options([
        'portrait' => 'Portrait',
        'landscape' => 'Landscape',
        'square' => 'Square',
      ])
      ->metaKey('_media_orientation');

    ob_start();
    $filter->renderListFilter();
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('id="media-filter-orientation"', $html);
    $this->assertStringContainsString('name="orientation"', $html);
    $this->assertStringContainsString('attachment-filters ' . LibraryFilters::FILTER_CLASS, $html);
    $this->assertStringContainsString('All orientations', $html);
    $this->assertStringContainsString('value="landscape" selected="selected"', $html);
  }

  public function testListFiltersWrapInTrack(): void
  {
    LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation')
      ->register();

    ob_start();
    LibraryFilters::renderListFilters('attachment', 'bar');
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('class="' . LibraryFilters::TRACK_CLASS . '"', $html);
    $this->assertStringContainsString('id="media-filter-orientation"', $html);
    $this->assertStringContainsString(LibraryFilters::CLEAR_CLASS, $html);
  }

  public function testBootWithoutFiltersRegistersHooks(): void
  {
    LibraryFilters::boot();

    $hooks = array_column(WpStubs::$actions, 'hook');
    $filterHooks = array_column(WpStubs::$filters, 'hook');

    $this->assertContains('restrict_manage_posts', $hooks);
    $this->assertContains('pre_get_posts', $hooks);
    $this->assertContains('wp_enqueue_media', $hooks);
    $this->assertContains('acf/input/admin_enqueue_scripts', $hooks);
    $this->assertContains('ajax_query_attachments_args', $filterHooks);
  }

  public function testListBarRendersClearWithoutCustomFilters(): void
  {
    LibraryFilters::boot();

    ob_start();
    LibraryFilters::renderListFilters('attachment', 'bar');
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('class="button-link ' . LibraryFilters::CLEAR_CLASS . '"', $html);
    $this->assertStringContainsString('Clear', $html);
    $this->assertStringContainsString('upload.php', $html);
    $this->assertStringContainsString(' hidden', $html);
    $this->assertStringNotContainsString(LibraryFilters::TRACK_CLASS, $html);
  }

  public function testListClearIsVisibleWhenCoreFilterActive(): void
  {
    $_GET['attachment-filter'] = 'image';
    LibraryFilters::boot();

    ob_start();
    LibraryFilters::renderListFilters('attachment', 'bar');
    $html = (string) ob_get_clean();

    $this->assertStringContainsString(LibraryFilters::CLEAR_CLASS, $html);
    $this->assertStringNotContainsString(' hidden', $html);
  }

  public function testListClearKeepsModeAndSearch(): void
  {
    $_GET['mode'] = 'list';
    $_GET['s'] = 'logo';
    LibraryFilters::boot();

    ob_start();
    LibraryFilters::renderListFilters('attachment', 'bar');
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('mode=list', $html);
    $this->assertStringContainsString('s=logo', $html);
  }

  public function testRegisterBootsSharedHooks(): void
  {
    LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation')
      ->register();

    $hooks = array_column(WpStubs::$actions, 'hook');
    $filterHooks = array_column(WpStubs::$filters, 'hook');

    $this->assertContains('restrict_manage_posts', $hooks);
    $this->assertContains('pre_get_posts', $hooks);
    $this->assertContains('wp_enqueue_media', $hooks);
    $this->assertContains('acf/input/admin_enqueue_scripts', $hooks);
    $this->assertContains('ajax_query_attachments_args', $filterHooks);
  }

  public function testListFiltersSkipTopTablenav(): void
  {
    LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation')
      ->register();

    ob_start();
    LibraryFilters::renderListFilters('attachment', 'top');
    LibraryFilters::renderListFilters('post', 'bar');
    $html = (string) ob_get_clean();

    $this->assertSame('', $html);
  }

  public function testAjaxQueryReadsRequestQueryVar(): void
  {
    LibraryFilter::make('orientation')
      ->options(['square' => 'Square'])
      ->metaKey('_media_orientation')
      ->register();

    $_REQUEST['query'] = ['orientation' => 'square'];

    $args = LibraryFilters::filterAjaxQuery(['post_type' => 'attachment']);

    $this->assertSame('square', $args['meta_query'][0]['value']);
  }

  public function testFilterListQueryAppliesMeta(): void
  {
    LibraryFilter::make('orientation')
      ->options(['portrait' => 'Portrait'])
      ->metaKey('_media_orientation')
      ->register();

    $_GET['orientation'] = 'portrait';
    $_REQUEST['orientation'] = 'portrait';
    $GLOBALS['pagenow'] = 'upload.php';

    $query = new WP_Query();
    $query->query_vars['post_type'] = 'attachment';
    LibraryFilters::filterListQuery($query);

    $this->assertSame('portrait', $query->query_vars['meta_query'][0]['value']);
  }

  public function testRegisterRequiresQueryOrMetaKey(): void
  {
    $this->expectException(InvalidArgumentException::class);

    LibraryFilter::make('orientation')->register();
  }

  public function testToJsIncludesOptions(): void
  {
    $js = LibraryFilter::make('orientation')
      ->label('Filter by orientation')
      ->allLabel('All orientations')
      ->options(['portrait' => 'Portrait'])
      ->priority(-76)
      ->metaKey('_media_orientation')
      ->toJs();

    $this->assertSame('orientation', $js['id']);
    $this->assertSame('orientation', $js['queryVar']);
    $this->assertSame(-76, $js['priority']);
    $this->assertSame('select', $js['grid']);
    $this->assertSame(['portrait' => 'Portrait'], $js['options']);
    $this->assertSame([], $js['modelKeys']);
  }

  public function testToJsIncludesModelKeys(): void
  {
    $js = LibraryFilter::make('media_category')
      ->queryVar('media_category')
      ->modelKeys(['category_media', 'media_category'])
      ->query(static function (array $args): array {
        return $args;
      })
      ->toJs();

    $this->assertSame(['category_media', 'media_category'], $js['modelKeys']);
  }

  public function testToPublicSchemaMapsOptions(): void
  {
    $schema = LibraryFilter::make('orientation')
      ->label('Filter by orientation')
      ->allLabel('All orientations')
      ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
      ->metaKey('_media_orientation')
      ->toPublicSchema();

    $this->assertSame('orientation', $schema['id']);
    $this->assertSame('orientation', $schema['queryVar']);
    $this->assertFalse($schema['multiple']);
    $this->assertFalse($schema['supportsExclude']);
    $this->assertSame([
      ['value' => 'portrait', 'label' => 'Portrait'],
      ['value' => 'landscape', 'label' => 'Landscape'],
    ], $schema['options']);
  }

  public function testCustomGridDefaultsToMultipleAndSchemaOptions(): void
  {
    $schema = LibraryFilter::make('media_category')
      ->grid(LibraryFilter::GRID_CUSTOM)
      ->supportsExclude(true)
      ->schemaOptions(static function (): array {
        return [
          ['value' => '12', 'label' => 'Stone', 'parent' => '1', 'slug' => 'stone'],
        ];
      })
      ->query(static function (array $args): array {
        return $args;
      })
      ->toPublicSchema();

    $this->assertTrue($schema['multiple']);
    $this->assertTrue($schema['supportsExclude']);
    $this->assertSame('12', $schema['options'][0]['value']);
    $this->assertSame('1', $schema['options'][0]['parent']);
  }

  public function testApplyValuesMergesIncludeAndExclude(): void
  {
    LibraryFilter::make('media_category')
      ->grid(LibraryFilter::GRID_CUSTOM)
      ->query(static function (array $args, string $value): array {
        $args['applied'][] = $value;

        return $args;
      })
      ->register();

    $args = LibraryFilters::applyValues(['post_type' => 'attachment'], ['media_category' => '12,15']);
    $args = LibraryFilters::applyValues($args, ['media_category' => 'not:20']);

    $this->assertSame(['12,15', 'not:20'], $args['applied']);
  }

  public function testApplyValuesLooksUpByIdOrQueryVar(): void
  {
    LibraryFilter::make('orientation')
      ->queryVar('orient')
      ->metaKey('_media_orientation')
      ->options(['portrait' => 'Portrait'])
      ->register();

    $byId = LibraryFilters::applyValues([], ['orientation' => 'portrait']);
    $byVar = LibraryFilters::applyValues([], ['orient' => 'portrait']);

    $this->assertSame('portrait', $byId['meta_query'][0]['value']);
    $this->assertSame('portrait', $byVar['meta_query'][0]['value']);
  }

  public function testPublicSchemaListsRegisteredFilters(): void
  {
    LibraryFilter::make('orientation')
      ->options(['square' => 'Square'])
      ->metaKey('_media_orientation')
      ->register();

    $schema = LibraryFilters::publicSchema();

    $this->assertCount(1, $schema);
    $this->assertSame('orientation', $schema[0]['id']);
  }
}
