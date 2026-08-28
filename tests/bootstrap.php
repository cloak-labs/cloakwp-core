<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($autoload)) {
  require $autoload;
} else {
  spl_autoload_register(static function (string $class): void {
    $prefix = 'CloakWP\\Core\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }
    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
      require_once $path;
    }
  });
}

require_once __DIR__ . '/WpStubs.php';

if (!function_exists('sanitize_key')) {
  function sanitize_key($key): string
  {
    $key = strtolower((string) $key);

    return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
  }
}

if (!function_exists('sanitize_text_field')) {
  function sanitize_text_field($str): string
  {
    return trim((string) $str);
  }
}

if (!function_exists('wp_unslash')) {
  function wp_unslash($value)
  {
    return $value;
  }
}

if (!function_exists('esc_attr')) {
  function esc_attr($text): string
  {
    return htmlspecialchars((string) $text, ENT_QUOTES);
  }
}

if (!function_exists('esc_html')) {
  function esc_html($text): string
  {
    return htmlspecialchars((string) $text, ENT_QUOTES);
  }
}

if (!function_exists('add_action')) {
  function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
  {
    \CloakWP\Core\Tests\WpStubs::$actions[] = [
      'hook' => $hook,
      'callback' => $callback,
      'priority' => $priority,
    ];
  }
}

if (!function_exists('add_filter')) {
  function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
  {
    \CloakWP\Core\Tests\WpStubs::$filters[] = [
      'hook' => $hook,
      'callback' => $callback,
      'priority' => $priority,
    ];
  }
}

if (!function_exists('is_admin')) {
  function is_admin(): bool
  {
    return true;
  }
}

if (!function_exists('wp_script_is')) {
  function wp_script_is($handle, $status = 'enqueued'): bool
  {
    return false;
  }
}

if (!function_exists('wp_register_script')) {
  function wp_register_script($handle, $src, $deps = [], $ver = false, $args = []): void
  {
  }
}

if (!function_exists('wp_enqueue_script')) {
  function wp_enqueue_script($handle): void
  {
  }
}

if (!function_exists('wp_add_inline_script')) {
  function wp_add_inline_script($handle, $data, $position = 'after'): void
  {
  }
}

if (!function_exists('wp_localize_script')) {
  function wp_localize_script($handle, $objectName, $data): void
  {
  }
}

if (!class_exists('WP_Query')) {
  class WP_Query
  {
    /** @var array<string, mixed> */
    public array $query_vars = [];

    public function is_main_query(): bool
    {
      return true;
    }

    public function get(string $key)
    {
      return $this->query_vars[$key] ?? '';
    }

    public function set(string $key, $value): void
    {
      $this->query_vars[$key] = $value;
    }
  }
}
