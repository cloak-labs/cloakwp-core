<?php

declare(strict_types=1);

namespace CloakWP\Core\Gutenberg;

/**
 * Detects whether the current request is a block-editor screen.
 *
 * Should only be called as early as the `init` action at priority 4
 * (i.e. after post types are registered).
 */
final class BlockEditor
{
  public static function isActive(): bool
  {
    if (!is_admin()) {
      return false;
    }

    $postId = isset($_GET['post'])
      ? intval($_GET['post'])
      : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0);

    if ($postId && function_exists('use_block_editor_for_post')) {
      $post = get_post($postId);
      if ($post && use_block_editor_for_post($post)) {
        return true;
      }
    }

    if (function_exists('get_current_screen')) {
      $currentScreen = get_current_screen();
      if ($currentScreen && $currentScreen->is_block_editor()) {
        return true;
      }

      if (
        $currentScreen
        && method_exists($currentScreen, 'use_block_editor_for_post_type')
        && $currentScreen->use_block_editor_for_post_type()
      ) {
        return true;
      }
    }

    return false;
  }
}
