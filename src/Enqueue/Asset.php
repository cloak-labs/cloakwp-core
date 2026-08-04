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
  protected string $enqueueFunction = ''; // eg. 'wp_enqueue_script' or 'wp_enqueue_style'
  protected bool $adminOnly = false;

  public function __construct(string $handle)
  {
    $this->settings = [
      'handle' => $handle,
      'src' => '',
      'deps' => array(),
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
    $this->enqueueHooks = array_merge($this->enqueueHooks, $hookNames);
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
    $this->settings['deps'] = $deps;
    return $this;
  }

  /**
   * String specifying script version number, if it has one, which is added to the URL as a 
   * query string for cache busting purposes. If version is set to false, a version number 
   * is automatically added equal to current installed WordPress version. If set to null,
   * no version is added. 
   * 
   * Default: false
   */
  public function version(bool|string|null $ver): static
  {
    $this->settings['ver'] = $ver;
    return $this;
  }

  /**
   * Enqueue the script/stylesheet -- call this after the other configuration methods.
   */
  public function enqueue()
  {
    $enqueueFn = function () {
      if ($this->adminOnly && !is_admin()) {
        return;
      }
      $args = array_values($this->settings);
      if (is_callable($this->enqueueFunction)) {
        call_user_func($this->enqueueFunction, ...$args);
      } else {
        throw new InvalidArgumentException("The 'enqueueFunction' property for this Asset child class is not a valid function. Assign a value such as 'wp_enqueue_script'.");
      }
    };

    if (empty($this->enqueueHooks)) {
      $enqueueFn();
    } else {
      foreach ($this->enqueueHooks as $hook) {
        add_action($hook, $enqueueFn, $this->enqueuePriority);
      }
    }
  }
}
