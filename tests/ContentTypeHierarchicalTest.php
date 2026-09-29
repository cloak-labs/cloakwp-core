<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Content\ContentType;
use PHPUnit\Framework\TestCase;

final class ContentTypeHierarchicalTest extends TestCase
{
  public function testEnablingHierarchyAddsPageAttributes(): void
  {
    $type = new class('story') extends ContentType {
      public function exposedSettings(): array
      {
        return $this->settings;
      }
    };

    $type->hierarchical(true);

    $this->assertTrue($type->exposedSettings()['hierarchical']);
    $this->assertContains('page-attributes', $type->exposedSettings()['supports']);
  }

  public function testEnablingHierarchyDoesNotDuplicatePageAttributes(): void
  {
    $type = new class('story') extends ContentType {
      public function exposedSettings(): array
      {
        return $this->settings;
      }
    };

    $type->supports(['page-attributes'])->hierarchical(true);

    $this->assertSame(
      ['page-attributes'],
      array_values(array_filter(
        $type->exposedSettings()['supports'],
        static fn ($feature) => $feature === 'page-attributes'
      ))
    );
  }

  public function testDisablingHierarchyLeavesPageAttributes(): void
  {
    $type = new class('story') extends ContentType {
      public function exposedSettings(): array
      {
        return $this->settings;
      }
    };

    $type->supports(['title', 'page-attributes'])->hierarchical(false);

    $this->assertFalse($type->exposedSettings()['hierarchical']);
    $this->assertContains('page-attributes', $type->exposedSettings()['supports']);
  }

  public function testDisablingHierarchyDoesNotAddPageAttributes(): void
  {
    $type = new class('story') extends ContentType {
      public function exposedSettings(): array
      {
        return $this->settings;
      }
    };

    $type->hierarchical(false);

    $this->assertFalse($type->exposedSettings()['hierarchical']);
    $this->assertArrayNotHasKey('supports', $type->exposedSettings());
  }
}
