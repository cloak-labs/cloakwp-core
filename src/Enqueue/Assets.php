<?php

declare(strict_types=1);

namespace CloakWP\Core\Enqueue;

use InvalidArgumentException;

/**
 * Registers a collection of Script / Stylesheet assets.
 */
final class Assets
{
  /**
   * @param list<Stylesheet|Script> $assets
   */
  public static function enqueue(array $assets): void
  {
    foreach ($assets as $index => $asset) {
      if (!$asset instanceof Asset) {
        throw new InvalidArgumentException(
          sprintf('Asset at index %s must be a Script or Stylesheet instance.', (string) $index),
        );
      }
    }

    foreach ($assets as $asset) {
      $asset->enqueue();
    }
  }
}
