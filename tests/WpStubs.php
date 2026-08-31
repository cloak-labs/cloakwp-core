<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

final class WpStubs
{
  /** @var list<array{hook: mixed, callback: mixed, priority: mixed, accepted_args: mixed}> */
  public static array $actions = [];

  /** @var list<array{hook: mixed, callback: mixed, priority: mixed, accepted_args: mixed}> */
  public static array $filters = [];

  /** @var list<array{namespace: string, route: string, args: array<string, mixed>}> */
  public static array $restRoutes = [];

  /** @var list<array<string, mixed>> */
  public static array $enqueuedScripts = [];

  /** @var list<array<string, mixed>> */
  public static array $enqueuedStyles = [];

  public static bool $isAdmin = true;

  /** @var array<int, object> */
  public static array $posts = [];

  /** @var array<int, bool> */
  public static array $useBlockEditor = [];

  public static mixed $currentScreen = null;

  public static function reset(): void
  {
    self::$actions = [];
    self::$filters = [];
    self::$restRoutes = [];
    self::$enqueuedScripts = [];
    self::$enqueuedStyles = [];
    self::$isAdmin = true;
    self::$posts = [];
    self::$useBlockEditor = [];
    self::$currentScreen = null;
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];
  }
}
