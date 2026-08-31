<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Gutenberg\BlockEditor;
use PHPUnit\Framework\TestCase;

final class BlockEditorTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
    WpStubs::$isAdmin = false;
    WpStubs::$posts = [];
    WpStubs::$useBlockEditor = [];
    WpStubs::$currentScreen = null;
  }

  public function testReturnsFalseOutsideAdmin(): void
  {
    WpStubs::$isAdmin = false;

    $this->assertFalse(BlockEditor::isActive());
  }

  public function testDetectsViaGetPostParameter(): void
  {
    WpStubs::$isAdmin = true;
    $_GET['post'] = '42';
    WpStubs::$posts[42] = (object) ['ID' => 42];
    WpStubs::$useBlockEditor[42] = true;

    $this->assertTrue(BlockEditor::isActive());
  }

  public function testDetectsViaPostIdParameter(): void
  {
    WpStubs::$isAdmin = true;
    $_POST['post_ID'] = '99';
    WpStubs::$posts[99] = (object) ['ID' => 99];
    WpStubs::$useBlockEditor[99] = true;

    $this->assertTrue(BlockEditor::isActive());
  }

  public function testFallsBackToCurrentScreen(): void
  {
    WpStubs::$isAdmin = true;
    WpStubs::$currentScreen = new class {
      public function is_block_editor(): bool
      {
        return true;
      }
    };

    $this->assertTrue(BlockEditor::isActive());
  }
}
