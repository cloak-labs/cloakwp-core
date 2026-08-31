<?php

declare(strict_types=1);

namespace CloakWP\Core\Rest;

use InvalidArgumentException;

/**
 * Fluent configuration for one WordPress REST route.
 */
final class Route
{
  /** @var list<string> */
  private array $methods;

  private string $path;

  /** @var callable */
  private $handler;

  /** @var callable|null */
  private $permissionCallback = null;

  /** @var array<string, array<string, mixed>> */
  private array $arguments = [];

  private function __construct(string|array $methods, string $path, callable $handler)
  {
    $this->methods = self::normalizeMethods($methods);
    $this->path = self::validatePath($path);
    $this->handler = $handler;
  }

  public static function make(string|array $methods, string $path, callable $handler): self
  {
    return new self($methods, $path, $handler);
  }

  public static function get(string $path, callable $handler): self
  {
    return self::make('GET', $path, $handler);
  }

  public static function post(string $path, callable $handler): self
  {
    return self::make('POST', $path, $handler);
  }

  public static function put(string $path, callable $handler): self
  {
    return self::make('PUT', $path, $handler);
  }

  public static function patch(string $path, callable $handler): self
  {
    return self::make('PATCH', $path, $handler);
  }

  public static function delete(string $path, callable $handler): self
  {
    return self::make('DELETE', $path, $handler);
  }

  /**
   * Mark this route as intentionally available without authentication.
   */
  public function public(): self
  {
    $this->permissionCallback = static fn(): bool => true;

    return $this;
  }

  public function permission(callable $callback): self
  {
    $this->permissionCallback = $callback;

    return $this;
  }

  /**
   * @param array<string, array<string, mixed>> $arguments
   */
  public function args(array $arguments): self
  {
    foreach ($arguments as $name => $settings) {
      if (!is_string($name) || trim($name) === '') {
        throw new InvalidArgumentException('REST route argument names must be non-empty strings.');
      }

      if (!is_array($settings)) {
        throw new InvalidArgumentException(
          sprintf('REST route argument "%s" must be configured with an array.', $name),
        );
      }
    }

    $this->arguments = $arguments;

    return $this;
  }

  public function path(): string
  {
    return $this->path;
  }

  /**
   * @return array{
   *   methods: string|list<string>,
   *   callback: callable,
   *   permission_callback: callable,
   *   args: array<string, array<string, mixed>>
   * }
   */
  public function definition(): array
  {
    if ($this->permissionCallback === null) {
      throw new InvalidArgumentException(
        sprintf(
          'REST route "%s" requires public() or permission() before registration.',
          $this->path,
        ),
      );
    }

    return [
      'methods' => count($this->methods) === 1 ? $this->methods[0] : $this->methods,
      'callback' => $this->handler,
      'permission_callback' => $this->permissionCallback,
      'args' => $this->arguments,
    ];
  }

  /**
   * @return list<string>
   */
  private static function normalizeMethods(string|array $methods): array
  {
    $methods = is_string($methods)
      ? (preg_split('/\s*,\s*/', $methods) ?: [])
      : $methods;
    if ($methods === []) {
      throw new InvalidArgumentException('A REST route requires at least one HTTP method.');
    }

    $normalized = [];
    foreach ($methods as $method) {
      if (!is_string($method) || !preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $method)) {
        throw new InvalidArgumentException('REST route methods must be non-empty HTTP method names.');
      }

      $method = strtoupper($method);
      if (!in_array($method, $normalized, true)) {
        $normalized[] = $method;
      }
    }

    return $normalized;
  }

  private static function validatePath(string $path): string
  {
    if ($path === '' || $path[0] !== '/') {
      throw new InvalidArgumentException('A REST route path must be non-empty and begin with "/".');
    }

    return $path;
  }
}
