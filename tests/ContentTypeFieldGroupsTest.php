<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Content\ContentType;
use CloakWP\Core\Content\ContentModel;
use PHPUnit\Framework\TestCase;

final class ContentTypeFieldGroupsTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
    ContentModel::resetInstances();
    if (!class_exists(\Extended\ACF\Location::class)) {
      require_once __DIR__ . '/Support/Location.php';
    }
  }

  private function group(): object
  {
    return new class {
      public int $registrations = 0;
      public function location(array $locations): self { return $this; }
      public function register(): void { $this->registrations++; }
    };
  }

  public function testFieldGroupsRegisterByDefault(): void
  {
    $group = $this->group();
    (new ContentType('story'))->fieldGroups([$group])->register();
    do_action('acf/include_fields');
    $this->assertSame(1, $group->registrations);
  }

  public function testSkippingGroupsRetainsThePostTypeAndSaveHook(): void
  {
    $group = $this->group();
    $type = (new ContentType('story'))->fieldGroups([$group])->afterChange(static fn() => null);
    add_filter('cloakwp/content_type/register_field_groups', function (bool $register, ContentType $candidate) use ($type): bool {
      $this->assertSame($type, $candidate);
      return false;
    }, 10, 2);
    $type->register();
    do_action('acf/include_fields');
    $this->assertSame(0, $group->registrations);
    $this->assertContains('init', array_column(WpStubs::$actions, 'hook'));
    $this->assertContains('save_post_story', array_column(WpStubs::$actions, 'hook'));
  }

  public function testSkippedFactoriesDoNotConstructFields(): void
  {
    $calls = 0;
    $type = (new ContentType('story'))->fieldGroups(function () use (&$calls): array {
      $calls++;
      return [$this->group()];
    })->afterChange(static fn() => null);
    add_filter('cloakwp/content_type/register_field_groups', static fn(): bool => false);
    $type->register();
    do_action('acf/include_fields');
    $this->assertSame(0, $calls);
    $this->assertContains('init', array_column(WpStubs::$actions, 'hook'));
    $this->assertContains('save_post_story', array_column(WpStubs::$actions, 'hook'));
  }

  public function testAppendingPreservesFactoryOrderWithoutEagerConstruction(): void
  {
    $calls = 0;
    $first = $this->group();
    $second = $this->group();
    $third = $this->group();
    $type = (new ContentType('story'))->fieldGroups(function () use (&$calls, $first): array {
      $calls++;
      return [$first];
    })->addFieldGroup($second)->addFieldGroups(function () use (&$calls, $third): array {
      $calls++;
      return [$third];
    });
    $this->assertSame(0, $calls);
    $this->assertSame([$first, $second, $third], $type->getFieldGroups());
    $this->assertSame(2, $calls);
    $type->register();
    do_action('acf/include_fields');
    $this->assertSame([$first, $second, $third], $type->getFieldGroups());
    $this->assertSame(2, $calls);
    $this->assertSame([1, 1, 1], [$first->registrations, $second->registrations, $third->registrations]);
  }

  public function testChildOverrideDoesNotBuildReplacedParentFields(): void
  {
    $parentCalls = 0;
    $childCalls = 0;
    $child = $this->group();
    $type = (new ContentType('story'))->fieldGroups(function () use (&$parentCalls): array {
      $parentCalls++;
      return [$this->group()];
    })->fieldGroups(function () use (&$childCalls, $child): array {
      $childCalls++;
      return [$child];
    });
    $type->register();
    do_action('acf/include_fields');
    $this->assertSame(0, $parentCalls);
    $this->assertSame(1, $childCalls);
    $this->assertSame([$child], $type->getFieldGroups());
    $this->assertSame(1, $child->registrations);
  }

  public function testExplicitReadsAllowExistingChildThemeExtensions(): void
  {
    $group = $this->group();
    $type = (new ContentType('story'))->fieldGroups(static fn(): array => [$group]);
    $resolved = $type->getFieldGroups();
    $resolved[0]->location([['custom' => 'location']]);
    $this->assertSame($group, $type->getFieldGroups()[0]);
  }

  public function testFactoriesAreIndependentAcrossSiteModels(): void
  {
    $calls = [];
    foreach ([1, 2] as $site) {
      $type = (new ContentType('story'))->fieldGroups(function () use ($site, &$calls): array {
        $calls[] = $site;
        return [$this->group()];
      });
      ContentModel::forSite($site)->registerTypes([$type]);
    }
    $this->assertSame([], $calls);
    ContentModel::forSite(1)->getType('story')->getFieldGroups();
    $this->assertSame([1], $calls);
    ContentModel::forSite(2)->getType('story')->getFieldGroups();
    $this->assertSame([1, 2], $calls);
    ContentModel::resetInstances();
  }
  public function testFactoryAndPolicyWaitForAcfFieldRegistration(): void
  {
    $calls = 0;
    $allowed = true;
    $type = (new ContentType('story'))->fieldGroups(function () use (&$calls): array {
      $calls++;
      return [$this->group()];
    });
    add_filter('cloakwp/content_type/register_field_groups', static function () use (&$allowed): bool {
      return $allowed;
    });
    $type->register();
    $this->assertSame(0, $calls);
    $allowed = false;
    do_action('acf/include_fields');
    $this->assertSame(0, $calls);
  }

  public function testRegistrationAfterAcfIncludesImportsImmediately(): void
  {
    do_action('acf/include_fields');
    $group = $this->group();
    (new ContentType('late_story'))->fieldGroups(static fn(): array => [$group])->register();
    $this->assertSame(1, $group->registrations);
  }

}
