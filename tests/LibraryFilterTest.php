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
    $this->assertStringContainsString('All orientations', $html);
    $this->assertStringContainsString('value="landscape" selected="selected"', $html);
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
  }
}
