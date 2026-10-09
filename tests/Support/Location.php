<?php

declare(strict_types=1);

namespace Extended\ACF;

// The registration-policy tests don't export ACF locations or fields.
final class Location
{
  public static function where(string ...$args): self
  {
    return new self();
  }
}
