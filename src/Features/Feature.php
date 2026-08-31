<?php

declare(strict_types=1);

namespace CloakWP\Core\Features;

/**
 * Contract for opt-in WordPress feature modules.
 *
 * Features register their own hooks when `register()` is called — they never
 * auto-boot. Agency stacks and themes compose the features they want.
 */
interface Feature
{
  public function register(): void;
}
