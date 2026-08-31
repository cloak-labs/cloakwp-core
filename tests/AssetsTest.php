<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Enqueue\Assets;
use CloakWP\Core\Enqueue\Script;
use CloakWP\Core\Enqueue\Stylesheet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
  }

  public function testEnqueueRegistersHookedAssets(): void
  {
    Assets::enqueue([
      Stylesheet::make('theme-editor')
        ->hooks(['enqueue_block_assets'])
        ->src('/editor.css')
        ->version('1.0.0'),
      Script::make('theme-editor-js')
        ->hooks(['enqueue_block_editor_assets'])
        ->priority(100)
        ->src('/editor.js')
        ->deps(['jquery'])
        ->inFooter(),
    ]);

    $hooks = array_column(WpStubs::$actions, 'hook');
    $priorities = array_column(WpStubs::$actions, 'priority');

    $this->assertContains('enqueue_block_assets', $hooks);
    $this->assertContains('enqueue_block_editor_assets', $hooks);
    $this->assertContains(100, $priorities);
  }

  public function testEnqueueRejectsInvalidEntries(): void
  {
    try {
      Assets::enqueue([
        Script::make('valid')->hooks(['wp_enqueue_scripts']),
        'not-an-asset',
      ]);
      $this->fail('Expected invalid assets to be rejected.');
    } catch (InvalidArgumentException $exception) {
      $this->assertStringContainsString('index 1', $exception->getMessage());
    }

    $this->assertSame([], WpStubs::$actions);
  }

  public function testEnqueueForwardsSettingsWithoutArrayOrderCoupling(): void
  {
    Script::make('app')
      ->src('/app.js')
      ->deps(['jquery'])
      ->version('2.0.0')
      ->loadingStrategy('defer')
      ->inFooter()
      ->enqueue();

    Stylesheet::make('app')
      ->src('/app.css')
      ->deps(['base'])
      ->version(null)
      ->media('print')
      ->enqueue();

    $this->assertSame([
      'handle' => 'app',
      'src' => '/app.js',
      'deps' => ['jquery'],
      'ver' => '2.0.0',
      'args' => [
        'strategy' => 'defer',
        'in_footer' => true,
      ],
    ], WpStubs::$enqueuedScripts[0]);
    $this->assertSame([
      'handle' => 'app',
      'src' => '/app.css',
      'deps' => ['base'],
      'ver' => null,
      'media' => 'print',
    ], WpStubs::$enqueuedStyles[0]);
  }

  public function testVersionAcceptsFilemtimeIntegers(): void
  {
    Script::make('app')
      ->src('/app.js')
      ->version(1785777211)
      ->enqueue();

    $this->assertSame('1785777211', WpStubs::$enqueuedScripts[0]['ver']);
  }

  public function testAssetConfigurationRejectsInvalidValues(): void
  {
    $this->expectException(InvalidArgumentException::class);

    Script::make('app')->hooks(['']);
  }
}
