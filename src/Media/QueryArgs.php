<?php

declare(strict_types=1);

namespace CloakWP\Core\Media;

/**
 * Merge extra tax_query / meta_query clauses onto WP_Query args
 * without clobbering an existing relation or sibling clauses.
 */
final class QueryArgs
{
  /**
   * @param array<int|string, mixed> $existing
   * @param array<string, mixed> $clause
   * @return array<int|string, mixed>
   */
  public static function mergeTaxQuery(array $existing, array $clause): array
  {
    return self::merge($existing, $clause);
  }

  /**
   * @param array<int|string, mixed> $existing
   * @param array<string, mixed> $clause
   * @return array<int|string, mixed>
   */
  public static function mergeMetaQuery(array $existing, array $clause): array
  {
    return self::merge($existing, $clause);
  }

  /**
   * @param array<int|string, mixed> $existing
   * @param array<string, mixed> $clause
   * @return array<int|string, mixed>
   */
  private static function merge(array $existing, array $clause): array
  {
    if ($existing === []) {
      return [$clause];
    }

    if (!isset($existing['relation'])) {
      $existing = array_merge(
        ['relation' => 'AND'],
        array_is_list($existing) ? $existing : array_values($existing),
      );
    }

    $existing[] = $clause;

    return $existing;
  }
}
