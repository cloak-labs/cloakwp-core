<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Features\MediaLibraryFilters;
use CloakWP\Core\Media\LibraryFilters;
use PHPUnit\Framework\TestCase;

final class MediaLibraryFiltersFeatureTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
    LibraryFilters::reset();
  }

  public function testExplicitFeatureBootPreservesMediaLibraryHooks(): void
  {
    $feature = MediaLibraryFilters::make();

    $feature->register();
    $feature->register();

    $hooks = array_column(WpStubs::$actions, 'hook');
    $filterHooks = array_column(WpStubs::$filters, 'hook');

    $this->assertContains('restrict_manage_posts', $hooks);
    $this->assertContains('wp_enqueue_media', $hooks);
    $this->assertContains('ajax_query_attachments_args', $filterHooks);
    $this->assertSame(1, count(array_filter(
      $hooks,
      static fn(string $hook): bool => $hook === 'restrict_manage_posts',
    )));
  }

  public function testComposerAutoloadHasNoMediaHookSideEffects(): void
  {
    $composer = json_decode(
      (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
      true,
      flags: JSON_THROW_ON_ERROR,
    );

    $this->assertArrayNotHasKey('files', $composer['autoload']);
    $this->assertSame([], WpStubs::$actions);
    $this->assertSame([], WpStubs::$filters);
  }
}
