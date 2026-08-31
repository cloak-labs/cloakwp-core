<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Content\ContentModel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContentModelMultisiteTest extends TestCase
{
  protected function setUp(): void
  {
    ContentModel::resetInstances();
  }

  public function testContentModelsAreScopedBySiteId(): void
  {
    $siteOne = ContentModel::forSite(1);
    $siteTwo = ContentModel::forSite(2);

    $this->assertSame($siteOne, ContentModel::forSite(1));
    $this->assertSame($siteTwo, ContentModel::forSite(2));
    $this->assertNotSame($siteOne, $siteTwo);
  }

  public function testSiteIdsMustBePositive(): void
  {
    $this->expectException(InvalidArgumentException::class);

    ContentModel::forSite(0);
  }
}
