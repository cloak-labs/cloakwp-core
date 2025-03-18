<?php

namespace CloakWP\Core;

use DeepCopy\DeepCopy;
use WP_Theme_JSON_Resolver;

enum PostReturnType: string
{
  case Objects = 'objects';
  case Names = 'names';
}

class Utils
{

  /*
    Helper function used by the CloakWP plugin to log errors or other details to the WP error log
  */
  public static function log($log)
  {
    if (defined('CLOAKWP_DEBUG') && !CLOAKWP_DEBUG) {
      return;
    }

    if (is_array($log) || is_object($log)) {
      error_log(print_r($log, true));
    } else {
      error_log($log);
    }
  }

  /**
   * Useful for throwing in a WP post of multiple formats and knowing you'll get it back as a WP_Post object.
   */
  public static function asPostObject(int|\WP_Post|array $input): \WP_Post|null
  {
    // If input is an integer, assume it's a post ID
    if (is_int($input)) {
      return get_post($input);
    }

    // If input is a WP_Post object, return it as is
    if (is_a($input, 'WP_Post')) {
      return $input;
    }

    // If input is an array, try to get the post by ID
    if (is_array($input)) {
      $post_id = $input['id'] ?? null;
      if ($post_id) {
        return get_post($post_id);
      }
    }

    // If none of the above, return null
    return null;
  }

  /* 
    Returns the given post's full URL pathname, eg. `/blog/post-slug`
    Works for both published and draft posts by respecting the site's permalink structure
  */
  public static function getPostPathname($post_id)
  {
    $post = get_post($post_id);
    if (!$post)
      return null;

    // For published posts, use the normal permalink
    if ($post->post_status === 'publish') {
      return parse_url(get_permalink($post_id), PHP_URL_PATH);
    }

    // For drafts, we'll create a temporary copy of the post with 'publish' status
    $temp_post = clone $post;
    $temp_post->post_status = 'publish';

    // Ensure post_name (slug) is set
    if (empty($temp_post->post_name)) {
      $temp_post->post_name = sanitize_title($temp_post->post_title);
    }

    // Temporarily override the post in WordPress's cache
    $GLOBALS['post'] = $temp_post;

    // Get the permalink as if it were published
    $permalink = get_permalink($temp_post);

    // Restore the original post
    $GLOBALS['post'] = $post;

    return parse_url($permalink, PHP_URL_PATH);
  }

  /* 
    Given an author ID, return an object containing its regularly needed fields
  */
  public static function getPrettyAuthor($id)
  {
    if (!$id || is_bool($id) || (!is_numeric($id) && !is_string($id)))
      return null;

    $author = get_user_by('ID', $id);
    $user_meta = get_metadata('user', $author->ID);
    $desired_meta = apply_filters('cloakwp/author_format/included_meta', [], $user_meta);

    $final_meta = [];
    $acf = [];
    if ($user_meta) {
      foreach ($user_meta as $key => $value) {
        $is_acf_field = isset($user_meta["_$key"]);
        $is_acf_key = str_starts_with($key, "_") && isset($user_meta[substr($key, 1)]);
        // If an ACF reference exists for this value, add it to the $acf array.
        if ($is_acf_field) {
          $acf_obj = get_field_object($key, 'user_' . $author->ID);
          if (is_array($acf_obj)) {
            $acf[$key] = $acf_obj['value'];
          }
        } else if (!$is_acf_key && ($desired_meta === true || in_array($key, $desired_meta))) {
          $final_meta[$key] = $value[0];
        }
      }
    }

    return $author ? array(
      'id' => $author->ID,
      'slug' => $author->user_nicename,
      'display_name' => $author->display_name,
      'meta' => $final_meta,
      'acf' => $acf,
    ) : null;
  }

  /**
   * Function to count and return the total number of posts of a given type; it only counts them instead of retrieving them, ensuring efficiency.
   */
  public static function countPosts(string $postType): int
  {
    // Set up the query arguments
    $args = array(
      'post_type' => $postType,
      'posts_per_page' => -1,  // Retrieves all posts
      'fields' => 'ids' // Retrieve only the IDs for quicker execution
    );

    // Create a new WP_Query instance
    $query = new \WP_Query($args);

    // Return the total number of posts found
    return $query->found_posts;
  }

  /**
   * Returns an array of the names of all custom post types (excludes builtins).
   */
  public static function getCustomPostTypes(PostReturnType $returnType = PostReturnType::Names, array $excluded = []): array
  {
    $cpts = get_post_types(['_builtin' => false], $returnType->value);

    if (!$cpts)
      return [];

    $excludedTypes = array_merge(
      array('acf-field-group', 'acf-field', 'acf-taxonomy', 'acf-post-type', 'acf-ui-options-page'),
      $excluded
    );

    foreach ($excludedTypes as $exclude) {
      if (isset($cpts[$exclude])) {
        unset($cpts[$exclude]);
      }
    }

    return $cpts;
  }

  /**
   * Returns an array of the names of all public post types.
   */
  public static function getPublicPostTypes(PostReturnType $returnType = PostReturnType::Names): array
  {
    return get_post_types(['public' => true], $returnType->value);
  }

  /**
   * Returns an array of the names of all post types that have the Gutenberg Editor enabled.
   */
  public static function getEditorPostTypes(): array
  {
    $postTypes = get_post_types(['show_in_rest' => true], 'names');
    $postTypes = array_values($postTypes);

    if (!function_exists('use_block_editor_for_post_type')) {
      require_once ABSPATH . 'wp-admin/includes/post.php';
    }

    $postTypes = array_filter($postTypes, 'use_block_editor_for_post_type');
    $postTypes[] = 'wp_navigation';
    $postTypes = array_filter($postTypes, 'post_type_exists');

    return $postTypes;
  }

  public static function getThemeFilePaths($dir, $options = [])
  {
    // Set default options
    $defaults = [
      'recurse' => false,
      'filename' => null,
      'extension' => 'php'
    ];
    // Merge passed options with defaults
    $options = array_merge($defaults, $options);

    // Get the paths for the child and parent theme directories
    $childThemeDir = get_stylesheet_directory() . $dir;
    $parentThemeDir = get_template_directory() . $dir;

    // Define the recursive function within the main function scope to avoid naming conflicts
    $scandirRecursive = function ($dir) use (&$scandirRecursive, $options) {
      $files = [];
      if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
          if ($item == '.' || $item == '..')
            continue;
          $path = $dir . '/' . $item;
          if (is_dir($path) && $options['recurse']) {
            $files = array_merge($files, $scandirRecursive($path));
          } elseif (is_file($path)) {
            if ($options['filename'] && basename($path) != $options['filename'])
              continue;
            if ($options['extension'] && pathinfo($path, PATHINFO_EXTENSION) != $options['extension'])
              continue;
            $files[] = $path;
          }
        }
      }
      return $files;
    };

    // Initialize arrays to store files
    $childFiles = [];
    $parentFiles = [];

    // Get the files from child directory if it exists
    if (is_dir($childThemeDir)) {
      $childFiles = $scandirRecursive($childThemeDir);
    }

    // Get the files from parent directory if it exists
    if (is_dir($parentThemeDir)) {
      $parentFiles = $scandirRecursive($parentThemeDir);
    }

    // Create an associative array with filenames as keys and paths as values
    $files = [];

    // Add child theme files
    foreach ($childFiles as $file) {
      $relativePath = str_replace(get_stylesheet_directory(), '', $file);
      $files[$relativePath] = $file;
    }

    // Add parent theme files, only if they are not overridden by child theme
    foreach ($parentFiles as $file) {
      $relativePath = str_replace(get_template_directory(), '', $file);
      if (!isset($files[$relativePath])) {
        $files[$relativePath] = $file;
      }
    }

    // Return the paths of the files
    return array_values($files);
  }

  /**
   * Returns the full path to a file in the theme, searching first in the child theme, then the parent theme.
   */
  public static function getThemeFilePath(string $path)
  {
    return get_theme_file_path($path);
  }

  /**
   * `requireAndCollect` provides a simple and effective way to require multiple files AND collect their return values into an array.
   */
  public static function requireAndCollect(array $filePaths): array
  {
    $contents = [];
    foreach ($filePaths as $path) {
      if (file_exists($path)) {
        $contents[] = require $path;
      } else {
        throw new \Exception("File not found: $path");
      }
    }
    return $contents;
  }

  /** 
   * A simple wrapper for requiring multiple files using a glob pattern. 
   * eg. Utils::requireGlob(get_stylesheet_directory() . '/models/*.php'); // will require all PHP files within your child theme's `models/` folder
   */
  public static function requireGlob(string $pathGlob)
  {
    $files = glob($pathGlob);
    foreach ($files as $file) {
      require_once $file;
    }
  }

  /**
   * A function that deeply copies Objects (class instances) and Arrays
   */
  public static function deepCopy($var)
  {
    static $copier = null;

    if (null === $copier) {
      $copier = new DeepCopy(true);
    }

    try {
      $copy = $copier->copy($var);
      return $copy;
    } catch (\Exception $err) {
      Utils::log("Caught Error while running deepCopy: {$err}");
    }

    return $var;
  }

  /**
   * filterArrayByKeys takes an array of associative arrays and an array of field names, then returns a cleaned version of the 1st array where each associative array only contains the properties defined in the 2nd array.
   * Note: you can also rename any selected property by passing the keys array like so: filterArrayByKeys([...], ['field_x' => 'new_field_name', ...])
   */
  public static function filterArrayByKeys(array $array, array $keys)
  {
    return array_map(function ($item) use ($keys) {
      $filteredItem = [];
      foreach ($keys as $originalKey => $newKey) {
        if (is_int($originalKey)) {
          // If the key is an integer, use it as the original key and the value as the new key
          $originalKey = $newKey;
        }
        if (array_key_exists($originalKey, $item)) {
          $filteredItem[$newKey] = $item[$originalKey];
        }
      }
      return $filteredItem;
    }, $array);
  }
}
