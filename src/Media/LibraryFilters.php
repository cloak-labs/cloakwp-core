<?php

declare(strict_types=1);

namespace CloakWP\Core\Media;

use WP_Query;

/**
 * Registry + WordPress hooks for LibraryFilter instances.
 *
 * First register() boots list-table, ajax, and grid/modal assets once.
 */
final class LibraryFilters
{
  public const SCRIPT_HANDLE = 'cloakwp-media-library-filters';

  /** @var array<string, LibraryFilter> */
  private static array $filters = [];

  private static bool $booted = false;

  private static bool $localized = false;

  public static function add(LibraryFilter $filter): void
  {
    self::$filters[$filter->id()] = $filter;
    self::boot();
  }

  /**
   * @return array<string, LibraryFilter>
   */
  public static function all(): array
  {
    return self::$filters;
  }

  public static function get(string $id): ?LibraryFilter
  {
    return self::$filters[$id] ?? null;
  }

  /**
   * Reset static state (unit tests).
   */
  public static function reset(): void
  {
    self::$filters = [];
    self::$booted = false;
    self::$localized = false;
  }

  private static function boot(): void
  {
    if (self::$booted) {
      return;
    }

    self::$booted = true;

    add_action('restrict_manage_posts', [self::class, 'renderListFilters'], 10, 2);
    add_action('pre_get_posts', [self::class, 'filterListQuery']);
    add_filter('ajax_query_attachments_args', [self::class, 'filterAjaxQuery']);
    add_action('admin_enqueue_scripts', [self::class, 'registerAssets'], 1);
    add_action('admin_enqueue_scripts', [self::class, 'enqueueOnUploadScreen'], 20);
    add_action('wp_enqueue_media', [self::class, 'enqueue'], 20);
    add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueForAcf'], 20);
  }

  public static function renderListFilters(string $postType, string $which): void
  {
    if ($postType !== 'attachment' || $which !== 'bar') {
      return;
    }

    foreach (self::$filters as $filter) {
      $filter->renderListFilter();
    }
  }

  public static function filterListQuery(WP_Query $query): void
  {
    if (!is_admin() || !$query->is_main_query()) {
      return;
    }

    global $pagenow;
    if ($pagenow !== 'upload.php') {
      return;
    }

    $postType = $query->get('post_type');
    if ($postType && $postType !== 'attachment' && !(is_array($postType) && in_array('attachment', $postType, true))) {
      return;
    }

    $original = $query->query_vars;
    $args = $original;

    foreach (self::$filters as $filter) {
      $value = $filter->readValue($args);
      if ($value === '') {
        continue;
      }
      $args = $filter->applyToArgs($args, $value);
    }

    self::syncQueryVars($query, $original, $args);
  }

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public static function filterAjaxQuery(array $args): array
  {
    foreach (self::$filters as $filter) {
      $value = $filter->readValue($args);
      if ($value === '') {
        continue;
      }
      $args = $filter->applyToArgs($args, $value);
    }

    return $args;
  }

  public static function registerAssets(): void
  {
    if (wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    $jsPath = self::jsPath();
    $version = is_readable($jsPath) ? (string) filemtime($jsPath) : '1';

    $deps = ['jquery', 'media-views'];
    if (wp_script_is('media-grid', 'registered') || wp_script_is('media-grid', 'enqueued')) {
      $deps[] = 'media-grid';
    }

    wp_register_script(self::SCRIPT_HANDLE, false, $deps, $version, true);

    if (is_readable($jsPath)) {
      $js = file_get_contents($jsPath);
      if (is_string($js) && $js !== '') {
        wp_add_inline_script(self::SCRIPT_HANDLE, $js, 'after');
      }
    }
  }

  public static function enqueueOnUploadScreen(string $hookSuffix): void
  {
    if (!in_array($hookSuffix, ['upload.php', 'post.php', 'post-new.php', 'media-upload.php', 'attachment'], true)) {
      return;
    }

    self::registerAssets();
    self::enqueue();
  }

  public static function enqueueForAcf(): void
  {
    if (function_exists('wp_enqueue_media')) {
      wp_enqueue_media();
    }
    self::enqueue();
  }

  public static function enqueue(): void
  {
    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      self::registerAssets();
    }

    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    wp_enqueue_script(self::SCRIPT_HANDLE);
    self::localize();
  }

  private static function localize(): void
  {
    if (self::$localized || !wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    $payload = [];
    foreach (self::$filters as $filter) {
      $payload[] = $filter->toJs();
    }

    $json = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
    if (!is_string($json) || $json === '') {
      $json = '[]';
    }

    wp_add_inline_script(
      self::SCRIPT_HANDLE,
      'window.cloakwpMediaLibraryFilters = ' . $json . ';',
      'before',
    );
    self::$localized = true;
  }

  /**
   * @param array<string, mixed> $original
   * @param array<string, mixed> $updated
   */
  private static function syncQueryVars(WP_Query $query, array $original, array $updated): void
  {
    foreach ($updated as $key => $value) {
      $query->set($key, $value);
    }

    foreach ($original as $key => $value) {
      if (!array_key_exists($key, $updated)) {
        unset($query->query_vars[$key]);
      }
    }
  }

  private static function jsPath(): string
  {
    return dirname(__DIR__, 2) . '/resources/js/media-library-filters.js';
  }
}
