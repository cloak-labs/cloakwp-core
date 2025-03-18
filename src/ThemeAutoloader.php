<?php

namespace CloakWP\Core;

class ThemeAutoloader
{
  const PARENT_NAMESPACE = 'ParentTheme\\';
  const THEME_NAMESPACE = 'Theme\\';

  public static function register()
  {
    // Custom theme autoloader for Theme namespace, allowing child theme overrides
    spl_autoload_register(function ($class) {
      // Handle ParentTheme namespace
      if (self::isNamespace($class, self::PARENT_NAMESPACE)) {
        self::loadParentThemeClass($class);
        return;
      }

      // Handle Theme namespace
      if (self::isNamespace($class, self::THEME_NAMESPACE)) {
        self::loadThemeClass($class);
      }
    });
  }

  /**
   * Check if class belongs to a specific namespace
   */
  private static function isNamespace($class, $namespace)
  {
    return strpos($class, $namespace) === 0;
  }

  /**
   * Get the relative path from a namespaced class
   */
  private static function getRelativePath($class, $namespacePrefix)
  {
    return str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($namespacePrefix)));
  }

  /**
   * Build a full file path from a relative path and a directory
   */
  private static function buildFilePath($directory, $relativePath)
  {
    return $directory . DIRECTORY_SEPARATOR . $relativePath . '.php';
  }

  /**
   * Check if a file exists and require it if it does
   */
  private static function requireFileIfExists($file)
  {
    if (file_exists($file)) {
      require_once $file;
      return true;
    }
    return false;
  }

  /**
   * Load class from ParentTheme namespace
   */
  private static function loadParentThemeClass($class)
  {
    $path = self::getRelativePath($class, self::PARENT_NAMESPACE);
    $file = self::buildFilePath(get_template_directory(), $path);

    if (file_exists($file)) {
      // We need to include the file content but modify the namespace to prevent name collisions.
      $content = file_get_contents($file);
      $content = str_replace('namespace ' . self::THEME_NAMESPACE, 'namespace ' . self::PARENT_NAMESPACE, $content);

      /**
       * Use eval to execute the modified code.
       * This is not ideal but necessary to prevent namespace name collisions. It will have 
       * a minor performance impact, so it's best to include/require parent theme classes 
       * directly rather than rely on autoload.
       */
      eval ('?>' . $content);
    }
  }

  /**
   * Load class from Theme namespace
   */
  private static function loadThemeClass($class)
  {
    $path = self::getRelativePath($class, self::THEME_NAMESPACE);

    // Check if we're in a child theme context
    if (is_child_theme()) {
      // First check if the class exists in the child theme
      $child_file = self::buildFilePath(get_stylesheet_directory(), $path);
      if (self::requireFileIfExists($child_file)) {
        return; // Stop here - don't load the parent version
      }
    }

    // Fall back to parent theme only if we haven't loaded a child theme version
    $parent_file = self::buildFilePath(get_template_directory(), $path);
    self::requireFileIfExists($parent_file);
  }
}