<?php

declare(strict_types=1);

namespace CloakWP\Core\Enqueue;

use InvalidArgumentException;

/**
 * A base class for the Script and Stylesheet child classes, enabling a better API 
 * for enqueueing custom CSS and JS files.
 */
abstract class Asset
{
  protected array $settings;
  protected array $enqueueHooks = [];
  protected int $enqueuePriority = 10;
  protected bool $adminOnly = false;

  public function __construct(string $handle)
  {
    if (trim($handle) === '') {
      throw new InvalidArgumentException('An asset handle must be a non-empty string.');
    }

    $this->settings = [
      'handle' => $handle,
      'src' => '',
      'deps' => [],
      'ver' => false,
    ];
  }

  /**
   * Create a new script/stylesheet asset. You must call the `enqueue` method to actually enqueue it.
   * @param string $handle - Name of the script. Should be unique.
   */
  public static function make(string $handle): static
  {
    return new static($handle);
  }

  /**
   * Specify the action hook(s) where this asset should be enqueued. 
   */
  public function hooks(array $hookNames): static
  {
    foreach ($hookNames as $hookName) {
      if (!is_string($hookName) || trim($hookName) === '') {
        throw new InvalidArgumentException('Asset hooks must be non-empty hook names.');
      }

      if (!in_array($hookName, $this->enqueueHooks, true)) {
        $this->enqueueHooks[] = $hookName;
      }
    }

    return $this;
  }

  /**
   * Priority used when registering this asset on its enqueue hook(s).
   * Useful when depending on handles registered later on the same hook
   *
   * Default: 10
   */
  public function priority(int $priority): static
  {
    $this->enqueuePriority = $priority;
    return $this;
  }

  /**
   * Only enqueue in wp-admin. Use with `enqueue_block_assets` so canvas/iframe
   * styles don't also load on the public front-end.
   */
  public function adminOnly(): static
  {
    $this->adminOnly = true;
    return $this;
  }

  /**
   * Full URL of the script/stylesheet, or path relative to the WordPress root directory.
   */
  public function src(string $src): static
  {
    $this->settings['src'] = $src;
    return $this;
  }

  /**
   * An array of registered script/stylesheet handles this script depends on.
   * 
   * Default: array()
   */
  public function deps(array $deps): static
  {
    foreach ($deps as $dependency) {
      if (!is_string($dependency) || trim($dependency) === '') {
        throw new InvalidArgumentException('Asset dependencies must be non-empty handles.');
      }
    }

    $this->settings['deps'] = $deps;
    return $this;
  }

  /**
   * Version query string for cache busting. `false` uses the WordPress version;
   * `null` omits a version. Integers (typical `filemtime()` cache-busters) are
   * stored as strings.
   *
   * Default: false
   */
  public function version(bool|string|int|null $ver): static
  {
    $this->settings['ver'] = is_int($ver) ? (string) $ver : $ver;
    return $this;
  }

  /**
   * Enqueue the script/stylesheet -- call this after the other configuration methods.
   */
  public function enqueue(): void
  {
    $enqueueFn = function (): void {
      if ($this->adminOnly && !is_admin()) {
        return;
      }

      $this->enqueueAsset();
    };

    if (empty($this->enqueueHooks)) {
      $enqueueFn();
    } else {
      foreach ($this->enqueueHooks as $hook) {
        add_action($hook, $enqueueFn, $this->enqueuePriority);
      }
    }
  }

  abstract protected function enqueueAsset(): void;
}
