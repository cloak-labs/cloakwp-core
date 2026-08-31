<?php

declare(strict_types=1);

namespace CloakWP\Core\Gutenberg;

use InvalidArgumentException;
use WP_Block_Type_Registry;

/**
 * Restricts which core Gutenberg blocks are available in the editor.
 *
 * Non-core blocks remain available and `core/block` supports pattern workflows
 * unless an earlier filter has already restricted or denied them.
 *
 * ```php
 * AllowedBlocks::make([
 *   'core/paragraph',
 *   'core/heading' => ['postTypes' => ['post', 'page']],
 * ])->register();
 * ```
 */
final class AllowedBlocks
{
  private array|bool $blocks;

  private bool $registered = false;

  private function __construct(array|bool $blocks)
  {
    self::validateBlocks($blocks);
    $this->blocks = $blocks;
  }

  public static function make(array|bool $blocks): self
  {
    return new self($blocks);
  }

  public function register(): self
  {
    if ($this->registered) {
      return $this;
    }

    $this->registered = true;
    add_filter('allowed_block_types_all', [$this, 'filter'], 10, 2);

    return $this;
  }

  /**
   * @param array|bool $allowedBlockTypes Value produced by earlier filters.
   * @param object|null $editorContext Editor context with an optional `post` property.
   * @return array|bool
   */
  public function filter(array|bool $allowedBlockTypes, ?object $editorContext = null): array|bool
  {
    return $this->resolve($allowedBlockTypes, $editorContext);
  }

  /**
   * Pure resolution logic — extracted for characterization / unit tests.
   *
   * @param array|bool $allowedBlockTypes Value produced by earlier filters.
   * @param object|null $editorContext Editor context with a `post` property.
   * @param list<string>|null $registeredBlockTypeKeys Optional override for tests.
   * @return array|bool
   */
  public function resolve(
    array|bool $allowedBlockTypes,
    ?object $editorContext = null,
    ?array $registeredBlockTypeKeys = null,
  ): array|bool
  {
    if ($allowedBlockTypes === false || $this->blocks === false) {
      return false;
    }

    if ($this->blocks === true) {
      return $allowedBlockTypes;
    }

    if ($registeredBlockTypeKeys === null) {
      $registeredBlockTypes = WP_Block_Type_Registry::get_instance()->get_all_registered();
      $registeredBlockTypeKeys = array_keys($registeredBlockTypes);
    }

    $currentPostType = $editorContext->post->post_type ?? null;
    $finalAllowedBlocks = array_filter(
      $registeredBlockTypeKeys,
      fn($b) => !str_starts_with($b, 'core/')
    );

    // Always include core/block (reusable blocks) so patterns still work.
    $finalAllowedBlocks[] = 'core/block';

    foreach ($this->blocks as $key => $value) {
      if (is_string($value)) {
        $finalAllowedBlocks[] = $value;
        continue;
      }

      $blockName = $key;
      if (isset($value['postTypes'])) {
        foreach ($value['postTypes'] as $postType) {
          if ($currentPostType === $postType) {
            $finalAllowedBlocks[] = $blockName;
            break;
          }
        }
        continue;
      }

      $finalAllowedBlocks[] = $blockName;
    }

    $finalAllowedBlocks = array_values(array_unique($finalAllowedBlocks));

    if (is_array($allowedBlockTypes)) {
      return array_values(array_intersect($finalAllowedBlocks, $allowedBlockTypes));
    }

    return $finalAllowedBlocks;
  }

  private static function validateBlocks(array|bool $blocks): void
  {
    if (is_bool($blocks)) {
      return;
    }

    foreach ($blocks as $key => $value) {
      if (is_string($value) && $value !== '') {
        continue;
      }

      if (!is_string($key) || $key === '' || !is_array($value)) {
        throw new InvalidArgumentException(
          'Allowed blocks must be block names or block-name keys with configuration arrays.',
        );
      }

      if (!array_key_exists('postTypes', $value)) {
        continue;
      }

      if (!is_array($value['postTypes'])) {
        throw new InvalidArgumentException('postTypes argument must be an array of post type slugs.');
      }

      foreach ($value['postTypes'] as $postType) {
        if (!is_string($postType) || $postType === '') {
          throw new InvalidArgumentException('postTypes must contain non-empty post type slugs.');
        }
      }
    }
  }
}
