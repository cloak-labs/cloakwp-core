<?php

declare(strict_types=1);

/**
 * Always boot Media Library filter chrome (Clear + toolbar layout),
 * even when no LibraryFilter instances are registered.
 *
 * WordPress may not have loaded yet (Bedrock autoloads this file from
 * wp-config). Pre-seed $wp_filter so plugins_loaded still fires boot().
 */

use CloakWP\Core\Media\LibraryFilters;

$callback = [LibraryFilters::class, 'boot'];

if (function_exists('add_action')) {
  add_action('plugins_loaded', $callback, 1);

  return;
}

if (!isset($GLOBALS['wp_filter']) || !is_array($GLOBALS['wp_filter'])) {
  $GLOBALS['wp_filter'] = is_array($GLOBALS['wp_filter'] ?? null) ? $GLOBALS['wp_filter'] : [];
}

$GLOBALS['wp_filter']['plugins_loaded'][1][] = [
  'accepted_args' => 1,
  'function' => $callback,
];
