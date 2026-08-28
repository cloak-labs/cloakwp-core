<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

final class WpStubs
{
  /** @var list<array{hook: mixed, callback: mixed, priority: mixed}> */
  public static array $actions = [];

  /** @var list<array{hook: mixed, callback: mixed, priority: mixed}> */
  public static array $filters = [];

  public static function reset(): void
  {
    self::$actions = [];
    self::$filters = [];
    $_GET = [];
    $_REQUEST = [];
  }
}
