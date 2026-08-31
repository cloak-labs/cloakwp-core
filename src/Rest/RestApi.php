<?php

declare(strict_types=1);

namespace CloakWP\Core\Rest;

use InvalidArgumentException;

/**
 * Registers a collection of routes under one WordPress REST namespace.
 */
final class RestApi
{
  private string $namespace;

  /** @var list<Route> */
  private array $routes = [];

  /** @var list<array{path: string, definition: array<string, mixed>}> */
  private array $routeDefinitions = [];

  private bool $registered = false;

  private bool $routesRegistered = false;

  private function __construct(string $namespace)
  {
    if (
      $namespace === ''
      || trim($namespace, '/') !== $namespace
      || !preg_match('/^[a-zA-Z0-9._-]+(?:\/[a-zA-Z0-9._-]+)*$/', $namespace)
    ) {
      throw new InvalidArgumentException(
        'A REST API namespace must be non-empty and must not begin or end with "/".',
      );
    }

    $this->namespace = $namespace;
  }

  public static function make(string $namespace): self
  {
    return new self($namespace);
  }

  /**
   * @param list<Route> $routes
   */
  public function routes(array $routes): self
  {
    $this->assertMutable();

    foreach ($routes as $route) {
      if (!$route instanceof Route) {
        throw new InvalidArgumentException('RestApi routes() accepts only Route instances.');
      }
    }

    $this->routes = $routes;

    return $this;
  }

  public function register(): self
  {
    if ($this->registered) {
      return $this;
    }

    $this->routeDefinitions = array_map(
      static fn(Route $route): array => [
        'path' => $route->path(),
        'definition' => $route->definition(),
      ],
      $this->routes,
    );

    $this->registered = true;
    add_action('rest_api_init', [$this, 'registerRoutes']);

    return $this;
  }

  public function registerRoutes(): void
  {
    if ($this->routesRegistered) {
      return;
    }

    foreach ($this->routeDefinitions as $route) {
      register_rest_route($this->namespace, $route['path'], $route['definition']);
    }

    $this->routesRegistered = true;
  }

  private function assertMutable(): void
  {
    if ($this->registered) {
      throw new InvalidArgumentException('Cannot change a RestApi after register().');
    }
  }
}
