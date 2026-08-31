<?php

declare(strict_types=1);

namespace CloakWP\Core\Features;

use CloakWP\Core\Media\LibraryFilters;

/**
 * Explicitly boots the shared Media Library filter chrome and hooks.
 */
final class MediaLibraryFilters implements Feature
{
  public static function make(): self
  {
    return new self();
  }

  public function register(): void
  {
    LibraryFilters::boot();
  }
}
