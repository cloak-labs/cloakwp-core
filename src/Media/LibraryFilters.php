<?php

declare(strict_types=1);

namespace CloakWP\Core\Media;

use WP_Query;

/**
 * Registry + WordPress hooks for LibraryFilter instances.
 *
 * Boot with CloakWP\Core\Features\MediaLibraryFilters so the Clear control
 * and toolbar layout load even when no custom filters exist.
 */
final class LibraryFilters
{
  public const SCRIPT_HANDLE = 'cloakwp-media-library-filters';

  public const STYLE_HANDLE = 'cloakwp-media-library-filters';

  public const FILTER_CLASS = 'cloakwp-media-library-filter';

  public const TRACK_CLASS = 'cloakwp-media-library-filter-track';

  public const CLEAR_CLASS = 'cloakwp-media-library-filters-clear';

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

  public static function boot(): void
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

    if (self::$filters !== []) {
      echo '<span class="' . esc_attr(self::TRACK_CLASS) . '">';
      foreach (self::$filters as $filter) {
        $filter->renderListFilter();
      }
      echo '</span>';
    }

    self::renderClearControl();
  }

  public static function renderClearControl(): void
  {
    $url = function_exists('admin_url') ? admin_url('upload.php') : 'upload.php';
    $keep = [];
    if (isset($_GET['mode'])) {
      $keep['mode'] = sanitize_key((string) wp_unslash($_GET['mode']));
    }
    if (isset($_GET['s']) && (string) wp_unslash($_GET['s']) !== '') {
      $keep['s'] = sanitize_text_field((string) wp_unslash($_GET['s']));
    }
    if ($keep !== [] && function_exists('add_query_arg')) {
      $url = add_query_arg($keep, $url);
    }

    printf(
      '<a class="button-link %s" href="%s"%s>%s</a>',
      esc_attr(self::CLEAR_CLASS),
      esc_url($url),
      self::listFiltersAreActive() ? '' : ' hidden',
      esc_html__('Clear'),
    );
  }

  public static function listFiltersAreActive(): bool
  {
    $attachment = isset($_GET['attachment-filter'])
      ? sanitize_text_field((string) wp_unslash($_GET['attachment-filter']))
      : '';
    if ($attachment !== '' && $attachment !== '0' && $attachment !== 'all') {
      return true;
    }

    $month = isset($_GET['m']) ? (string) wp_unslash($_GET['m']) : '';
    if ($month !== '' && $month !== '0') {
      return true;
    }

    foreach (self::$filters as $filter) {
      if ($filter->readValue() !== '') {
        return true;
      }
    }

    return false;
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
    $jsPath = self::jsPath();
    $cssPath = self::cssPath();
    $jsMtime = is_readable($jsPath) ? (int) filemtime($jsPath) : 0;
    $cssMtime = is_readable($cssPath) ? (int) filemtime($cssPath) : 0;
    $version = (string) max($jsMtime, $cssMtime, 1);

    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
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

    if (!wp_style_is(self::STYLE_HANDLE, 'registered')) {
      $styleDeps = [];
      if (wp_style_is('media-views', 'registered') || wp_style_is('wp-admin', 'registered')) {
        if (wp_style_is('media-views', 'registered')) {
          $styleDeps[] = 'media-views';
        } elseif (wp_style_is('wp-admin', 'registered')) {
          $styleDeps[] = 'wp-admin';
        }
      }

      wp_register_style(self::STYLE_HANDLE, false, $styleDeps, $version);

      if (is_readable($cssPath)) {
        $css = file_get_contents($cssPath);
        if (is_string($css) && $css !== '') {
          wp_add_inline_style(self::STYLE_HANDLE, $css);
        }
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
    self::boot();

    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      self::registerAssets();
    }

    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    wp_enqueue_script(self::SCRIPT_HANDLE);
    wp_enqueue_style(self::STYLE_HANDLE);
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

    $l10n = function_exists('wp_json_encode')
      ? wp_json_encode(['clear' => __('Clear')])
      : json_encode(['clear' => 'Clear']);
    if (!is_string($l10n) || $l10n === '') {
      $l10n = '{"clear":"Clear"}';
    }

    wp_add_inline_script(
      self::SCRIPT_HANDLE,
      'window.cloakwpMediaLibraryFilters = ' . $json . ';'
      . 'window.cloakwpMediaLibraryFilterL10n = ' . $l10n . ';',
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

  private static function cssPath(): string
  {
    return dirname(__DIR__, 2) . '/resources/css/media-library-filters.css';
  }
}
