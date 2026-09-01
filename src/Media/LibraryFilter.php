<?php

declare(strict_types=1);

namespace CloakWP\Core\Media;

use InvalidArgumentException;

/**
 * A Media Library filter (list view dropdown + grid/modal AttachmentFilters).
 *
 * Simple dropdowns only need options + a query (or a meta key). Custom UIs
 * (multi-select, extra controls) supply listRenderer() and grid('custom').
 * Custom toolbar views are collected into the shared scroll track automatically.
 *
 * @example
 * LibraryFilter::make('orientation')
 *   ->label('Filter by orientation')
 *   ->allLabel('All orientations')
 *   ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
 *   ->metaKey('_media_orientation')
 *   ->register();
 */
final class LibraryFilter
{
  public const GRID_SELECT = 'select';
  public const GRID_CUSTOM = 'custom';

  private string $id;
  private string $queryVar;
  private string $label = '';
  private string $allLabel = '';
  /** @var array<string, string> */
  private array $options = [];
  private int $priority = -72;
  private string $grid = self::GRID_SELECT;
  private ?string $metaKey = null;
  /** @var (callable(array<string, mixed>, string): array<string, mixed>)|null */
  private $queryCallback = null;
  /** @var (callable(string): void)|null */
  private $listRenderer = null;
  /** @var (callable(array<string, mixed>): ?string)|null */
  private $resolveValue = null;
  /** @var array<string, mixed> */
  private array $jsSettings = [];
  /** @var list<string> Extra Backbone/URL keys Clear should drop (custom grid UIs). */
  private array $modelKeys = [];
  private bool $supportsExclude = false;
  /** @var bool|null Null = default from grid (custom → multiple, select → single). */
  private ?bool $multiple = null;
  /**
   * @var (callable(): list<array{value: string, label: string, parent?: string|null, slug?: string}>)|null
   */
  private $schemaOptionsCallback = null;
  private bool $registered = false;

  private function __construct(string $id)
  {
    $id = sanitize_key($id);
    if ($id === '') {
      throw new InvalidArgumentException('LibraryFilter id must be a non-empty key.');
    }

    $this->id = $id;
    $this->queryVar = $id;
  }

  public static function make(string $id): self
  {
    return new self($id);
  }

  public function queryVar(string $queryVar): self
  {
    $this->assertMutable();
    $queryVar = sanitize_key($queryVar);
    if ($queryVar === '') {
      throw new InvalidArgumentException('LibraryFilter queryVar must be a non-empty key.');
    }
    $this->queryVar = $queryVar;

    return $this;
  }

  public function label(string $label): self
  {
    $this->assertMutable();
    $this->label = $label;

    return $this;
  }

  public function allLabel(string $allLabel): self
  {
    $this->assertMutable();
    $this->allLabel = $allLabel;

    return $this;
  }

  /**
   * @param array<string, string> $options value => label
   */
  public function options(array $options): self
  {
    $this->assertMutable();
    $this->options = $options;

    return $this;
  }

  /**
   * Toolbar priority. Core type filter is -80, date is -75.
   */
  public function priority(int $priority): self
  {
    $this->assertMutable();
    $this->priority = $priority;

    return $this;
  }

  /**
   * @param self::GRID_SELECT|self::GRID_CUSTOM $grid
   */
  public function grid(string $grid): self
  {
    $this->assertMutable();
    if ($grid !== self::GRID_SELECT && $grid !== self::GRID_CUSTOM) {
      throw new InvalidArgumentException('LibraryFilter grid must be "select" or "custom".');
    }
    $this->grid = $grid;

    return $this;
  }

  /**
   * Store the selected value as attachment post meta and filter with meta_query.
   * Ignored when query() is also set.
   */
  public function metaKey(string $metaKey): self
  {
    $this->assertMutable();
    $this->metaKey = $metaKey;

    return $this;
  }

  /**
   * Apply the selected value to WP_Query / ajax_query_attachments_args.
   *
   * @param callable(array<string, mixed>, string): array<string, mixed> $callback
   */
  public function query(callable $callback): self
  {
    $this->assertMutable();
    $this->queryCallback = $callback;

    return $this;
  }

  /**
   * Custom list-view HTML. Called from restrict_manage_posts on the media bar.
   * Default renders a <select> from options().
   *
   * @param callable(string $selected): void $callback
   */
  public function listRenderer(callable $callback): self
  {
    $this->assertMutable();
    $this->listRenderer = $callback;

    return $this;
  }

  /**
   * Override how the current filter value is read from the request / query args.
   *
   * @param callable(array<string, mixed>): ?string $callback
   */
  public function resolveValue(callable $callback): self
  {
    $this->assertMutable();
    $this->resolveValue = $callback;

    return $this;
  }

  /**
   * Extra data localized onto this filter for custom grid JS.
   *
   * @param array<string, mixed> $settings
   */
  public function jsSettings(array $settings): self
  {
    $this->assertMutable();
    $this->jsSettings = $settings;

    return $this;
  }

  /**
   * Backbone / URL keys this filter writes besides queryVar().
   *
   * Select filters only need queryVar. Custom grid UIs that set a different
   * model key (e.g. a taxonomy slug) must list those keys so Clear can
   * unset them for every consumer, not just this plugin.
   *
   * @param list<string> $keys
   */
  public function modelKeys(array $keys): self
  {
    $this->assertMutable();
    $clean = [];
    foreach ($keys as $key) {
      $key = sanitize_key((string) $key);
      if ($key !== '' && !in_array($key, $clean, true)) {
        $clean[] = $key;
      }
    }
    $this->modelKeys = $clean;

    return $this;
  }

  /**
   * Whether this filter understands exclude encoding (`not:value`).
   */
  public function supportsExclude(bool $supports = true): self
  {
    $this->assertMutable();
    $this->supportsExclude = $supports;

    return $this;
  }

  /**
   * Whether the public/frontend schema treats this filter as multi-select.
   * Defaults to true for custom grid UIs and false for select dropdowns.
   */
  public function multiple(bool $multiple = true): self
  {
    $this->assertMutable();
    $this->multiple = $multiple;

    return $this;
  }

  /**
   * Options for the public schema. Used when options() is empty (custom UIs).
   *
   * @param callable(): list<array{value: string, label: string, parent?: string|null, slug?: string}> $callback
   */
  public function schemaOptions(callable $callback): self
  {
    $this->assertMutable();
    $this->schemaOptionsCallback = $callback;

    return $this;
  }

  public function id(): string
  {
    return $this->id;
  }

  public function getQueryVar(): string
  {
    return $this->queryVar;
  }

  public function getLabel(): string
  {
    return $this->label !== '' ? $this->label : sprintf('Filter by %s', $this->id);
  }

  public function getAllLabel(): string
  {
    if ($this->allLabel !== '') {
      return $this->allLabel;
    }

    $readable = str_replace(['_', '-'], ' ', $this->id);

    return sprintf('All %s', $readable);
  }

  /**
   * @return array<string, string>
   */
  public function getOptions(): array
  {
    return $this->options;
  }

  public function getPriority(): int
  {
    return $this->priority;
  }

  public function getGrid(): string
  {
    return $this->grid;
  }

  public function getMetaKey(): ?string
  {
    return $this->metaKey;
  }

  /**
   * @return array<string, mixed>
   */
  public function getJsSettings(): array
  {
    return $this->jsSettings;
  }

  /**
   * @return list<string>
   */
  public function getModelKeys(): array
  {
    return $this->modelKeys;
  }

  public function allowsExclude(): bool
  {
    return $this->supportsExclude;
  }

  public function isMultiple(): bool
  {
    if ($this->multiple !== null) {
      return $this->multiple;
    }

    return $this->grid === self::GRID_CUSTOM;
  }

  /**
   * @return list<array{value: string, label: string, parent?: string|null, slug?: string}>
   */
  public function resolveSchemaOptions(): array
  {
    if ($this->schemaOptionsCallback !== null) {
      $rows = ($this->schemaOptionsCallback)();
      if (!is_array($rows)) {
        return [];
      }

      $out = [];
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $value = (string) ($row['value'] ?? '');
        if ($value === '') {
          continue;
        }
        $item = [
          'value' => $value,
          'label' => (string) ($row['label'] ?? $value),
        ];
        if (array_key_exists('parent', $row) && $row['parent'] !== null && $row['parent'] !== '') {
          $item['parent'] = (string) $row['parent'];
        }
        if (isset($row['slug']) && $row['slug'] !== '') {
          $item['slug'] = (string) $row['slug'];
        }
        $out[] = $item;
      }

      return $out;
    }

    $out = [];
    foreach ($this->options as $value => $label) {
      $out[] = [
        'value' => (string) $value,
        'label' => (string) $label,
      ];
    }

    return $out;
  }

  /**
   * @param array<string, mixed> $args
   */
  public function readValue(array $args = []): string
  {
    if ($this->resolveValue !== null) {
      $value = ($this->resolveValue)($args);

      return $value === null ? '' : (string) $value;
    }

    return self::valueFromRequest($this->queryVar, $args);
  }

  /**
   * Read a query var from list GET, ajax request, or leftover WP_Query args.
   *
   * wp_ajax_query_attachments() strips unknown keys before ajax_query_attachments_args,
   * so custom vars must be taken from $_REQUEST['query'] rather than $args.
   *
   * @param array<string, mixed> $args
   */
  public static function valueFromRequest(string $queryVar, array $args = []): string
  {
    $raw = null;

    if (isset($_REQUEST[$queryVar])) {
      $raw = wp_unslash($_REQUEST[$queryVar]);
    } elseif (isset($_REQUEST['query']) && is_array($_REQUEST['query']) && isset($_REQUEST['query'][$queryVar])) {
      $raw = wp_unslash($_REQUEST['query'][$queryVar]);
    } elseif (array_key_exists($queryVar, $args) && $args[$queryVar] !== null) {
      $raw = $args[$queryVar];
    }

    if (is_array($raw)) {
      $raw = implode(',', array_map('strval', $raw));
    }

    if ($raw === null) {
      return '';
    }

    $value = sanitize_text_field((string) $raw);

    return $value === '0' ? '' : $value;
  }

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public function applyToArgs(array $args, string $value): array
  {
    if ($value === '') {
      return $args;
    }

    unset($args[$this->queryVar]);

    if ($this->queryCallback !== null) {
      return ($this->queryCallback)($args, $value);
    }

    if ($this->metaKey !== null && $this->metaKey !== '') {
      if ($this->options !== [] && !array_key_exists($value, $this->options)) {
        return $args;
      }

      $args['meta_query'] = QueryArgs::mergeMetaQuery($args['meta_query'] ?? [], [
        'key' => $this->metaKey,
        'value' => $value,
        'compare' => '=',
      ]);

      return $args;
    }

    return $args;
  }

  public function renderListFilter(): void
  {
    $selected = $this->readValue();

    if ($this->listRenderer !== null) {
      ($this->listRenderer)($selected);

      return;
    }

    if ($this->options === []) {
      return;
    }

    $id = 'media-filter-' . $this->id;
    echo '<label class="screen-reader-text" for="' . esc_attr($id) . '">' . esc_html($this->getLabel()) . '</label>';
    echo '<select name="' . esc_attr($this->queryVar) . '" id="' . esc_attr($id) . '" class="attachment-filters ' . esc_attr(LibraryFilters::FILTER_CLASS) . '">';
    printf(
      '<option value=""%s>%s</option>',
      $selected === '' ? ' selected="selected"' : '',
      esc_html($this->getAllLabel()),
    );
    foreach ($this->options as $value => $label) {
      $value = (string) $value;
      printf(
        '<option value="%s"%s>%s</option>',
        esc_attr($value),
        $selected === $value ? ' selected="selected"' : '',
        esc_html((string) $label),
      );
    }
    echo '</select>';
  }

  /**
   * @return array<string, mixed>
   */
  public function toJs(): array
  {
    return [
      'id' => $this->id,
      'queryVar' => $this->queryVar,
      'label' => $this->getLabel(),
      'allLabel' => $this->getAllLabel(),
      'options' => $this->options,
      'priority' => $this->priority,
      'grid' => $this->grid,
      'modelKeys' => $this->modelKeys,
      'settings' => $this->jsSettings,
    ];
  }

  /**
   * Schema for decoupled frontends (image library, etc.).
   *
   * @return array{
   *   id: string,
   *   queryVar: string,
   *   label: string,
   *   allLabel: string,
   *   multiple: bool,
   *   supportsExclude: bool,
   *   options: list<array{value: string, label: string, parent?: string|null, slug?: string}>
   * }
   */
  public function toPublicSchema(): array
  {
    return [
      'id' => $this->id,
      'queryVar' => $this->queryVar,
      'label' => $this->getLabel(),
      'allLabel' => $this->getAllLabel(),
      'multiple' => $this->isMultiple(),
      'supportsExclude' => $this->supportsExclude,
      'options' => $this->resolveSchemaOptions(),
    ];
  }

  public function register(): self
  {
    if ($this->registered) {
      return $this;
    }

    if ($this->queryCallback === null && ($this->metaKey === null || $this->metaKey === '')) {
      throw new InvalidArgumentException(
        'LibraryFilter requires query() or metaKey() before register().',
      );
    }

    if ($this->grid === self::GRID_SELECT && $this->options === [] && $this->listRenderer === null) {
      throw new InvalidArgumentException(
        'LibraryFilter grid "select" requires options() or a custom listRenderer().',
      );
    }

    $this->registered = true;
    LibraryFilters::add($this);

    return $this;
  }

  private function assertMutable(): void
  {
    if ($this->registered) {
      throw new InvalidArgumentException('Cannot change a LibraryFilter after register().');
    }
  }
}
