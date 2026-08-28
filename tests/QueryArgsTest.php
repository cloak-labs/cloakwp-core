<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Media\QueryArgs;
use PHPUnit\Framework\TestCase;

final class QueryArgsTest extends TestCase
{
  public function testMergeIntoEmptyQuery(): void
  {
    $clause = ['key' => '_media_orientation', 'value' => 'portrait'];

    $this->assertSame([$clause], QueryArgs::mergeMetaQuery([], $clause));
    $this->assertSame([$clause], QueryArgs::mergeTaxQuery([], $clause));
  }

  public function testMergeAddsAndRelation(): void
  {
    $existing = [
      ['key' => 'foo', 'value' => 'bar'],
    ];
    $clause = ['key' => '_media_orientation', 'value' => 'square'];

    $result = QueryArgs::mergeMetaQuery($existing, $clause);

    $this->assertSame('AND', $result['relation']);
    $this->assertSame($existing[0], $result[0]);
    $this->assertSame($clause, $result[1]);
  }
}
