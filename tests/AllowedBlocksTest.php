<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Gutenberg\AllowedBlocks;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AllowedBlocksTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
  }

  public function testNonCoreBlocksAlwaysRemainAvailable(): void
  {
    $context = $this->editorContext('page');
    $registered = ['acf/hero', 'core/paragraph', 'core/heading', 'core/image'];

    $result = AllowedBlocks::make(['core/paragraph'])->resolve(true, $context, $registered);

    $this->assertContains('acf/hero', $result);
    $this->assertContains('core/paragraph', $result);
    $this->assertNotContains('core/heading', $result);
  }

  public function testCoreBlockIsAlwaysIncludedForPatterns(): void
  {
    $context = $this->editorContext('page');
    $result = AllowedBlocks::make([])->resolve(true, $context, ['acf/hero']);

    $this->assertContains('core/block', $result);
  }

  public function testPostTypeScopedBlocks(): void
  {
    $pageContext = $this->editorContext('page');
    $postContext = $this->editorContext('post');
    $blocks = [
      'core/heading' => ['postTypes' => ['page']],
      'core/paragraph',
    ];

    $onPage = AllowedBlocks::make($blocks)->resolve(true, $pageContext, ['acf/hero']);
    $onPost = AllowedBlocks::make($blocks)->resolve(true, $postContext, ['acf/hero']);

    $this->assertContains('core/heading', $onPage);
    $this->assertNotContains('core/heading', $onPost);
    $this->assertContains('core/paragraph', $onPost);
  }

  public function testTrueConfigurationPreservesUpstreamDecision(): void
  {
    $upstream = ['core/paragraph', 'acf/hero'];

    $this->assertSame($upstream, AllowedBlocks::make(true)->resolve($upstream));
    $this->assertFalse(AllowedBlocks::make(true)->resolve(false));
  }

  public function testFalseConfigurationDeniesAllBlocks(): void
  {
    $this->assertFalse(AllowedBlocks::make(false)->resolve(true));
    $this->assertFalse(AllowedBlocks::make(false)->resolve(['core/paragraph']));
  }

  public function testUpstreamAllowlistIsNeverBroadened(): void
  {
    $result = AllowedBlocks::make(['core/paragraph', 'core/heading'])
      ->resolve(
        ['core/paragraph', 'acf/allowed'],
        $this->editorContext('page'),
        ['acf/allowed', 'acf/denied', 'core/paragraph', 'core/heading'],
      );

    $this->assertSame(['acf/allowed', 'core/paragraph'], $result);
  }

  public function testUpstreamEmptyAllowlistRemainsEmpty(): void
  {
    $result = AllowedBlocks::make(['core/paragraph'])
      ->resolve([], null, ['acf/hero', 'core/paragraph']);

    $this->assertSame([], $result);
  }

  public function testNullEditorContextExcludesPostTypeScopedBlocks(): void
  {
    $result = AllowedBlocks::make([
      'core/heading' => ['postTypes' => ['page']],
      'core/paragraph',
    ])->resolve(true, null, ['acf/hero']);

    $this->assertSame(['acf/hero', 'core/block', 'core/paragraph'], $result);
  }

  public function testOutputIsDeduplicated(): void
  {
    $result = AllowedBlocks::make(['acf/hero', 'core/block', 'core/paragraph', 'core/paragraph'])
      ->resolve(true, null, ['acf/hero', 'acf/hero']);

    $this->assertSame(['acf/hero', 'core/block', 'core/paragraph'], $result);
  }

  public function testInvalidPostTypesThrows(): void
  {
    $this->expectException(\InvalidArgumentException::class);

    AllowedBlocks::make([
      'core/heading' => ['postTypes' => 'page'],
    ]);
  }

  public function testRegisterAttachesAllowedBlockTypesFilter(): void
  {
    $allowedBlocks = AllowedBlocks::make(['core/paragraph']);
    $allowedBlocks->register();
    $allowedBlocks->register();

    $this->assertCount(1, WpStubs::$filters);
    $this->assertSame('allowed_block_types_all', WpStubs::$filters[0]['hook']);
    $this->assertSame(10, WpStubs::$filters[0]['priority']);
    $this->assertSame(2, WpStubs::$filters[0]['accepted_args']);
  }

  private function editorContext(string $postType): object
  {
    $context = new stdClass();
    $context->post = new stdClass();
    $context->post->post_type = $postType;

    return $context;
  }
}
