<?php

declare(strict_types=1);

namespace CloakWP\Core\Tests;

use CloakWP\Core\Rest\RestApi;
use CloakWP\Core\Rest\Route;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RestApiTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
  }

  public function testGetRouteBuildsPublicConfigurationWithArguments(): void
  {
    $handler = static fn(): array => ['ok' => true];
    $route = Route::get('/menus', $handler)
      ->public()
      ->args([
        'location' => [
          'required' => true,
          'type' => 'string',
        ],
      ]);

    $definition = $route->definition();

    $this->assertSame('/menus', $route->path());
    $this->assertSame('GET', $definition['methods']);
    $this->assertSame($handler, $definition['callback']);
    $this->assertTrue($definition['permission_callback']());
    $this->assertSame('string', $definition['args']['location']['type']);
  }

  public function testExplicitPermissionCallbackIsPreserved(): void
  {
    $permission = static fn(object $request): bool => ($request->allowed ?? false) === true;
    $definition = Route::post('/menus', static fn(): null => null)
      ->permission($permission)
      ->definition();

    $this->assertSame($permission, $definition['permission_callback']);
    $this->assertTrue($definition['permission_callback']((object) ['allowed' => true]));
    $this->assertFalse($definition['permission_callback']((object) ['allowed' => false]));
  }

  public function testSupportsArbitraryMethodsAndInvokableHandlers(): void
  {
    $handler = new class {
      public function __invoke(): string
      {
        return 'handled';
      }
    };

    $definition = Route::make(['propfind', 'PATCH', 'propfind'], '/documents', $handler)
      ->public()
      ->definition();

    $this->assertSame(['PROPFIND', 'PATCH'], $definition['methods']);
    $this->assertSame($handler, $definition['callback']);
    $this->assertSame('handled', $definition['callback']());

    $this->assertSame(
      ['POST', 'PUT', 'PATCH'],
      Route::make('POST, PUT, PATCH', '/documents', $handler)->public()->definition()['methods'],
    );
  }

  public function testRegistrationIsDelayedUntilRestApiInitAndForwarded(): void
  {
    RestApi::make('cloakwp/v1')
      ->routes([
        Route::get('/menus', static fn(): array => [])->public(),
      ])
      ->register();

    $this->assertSame([], WpStubs::$restRoutes);
    $this->assertCount(1, WpStubs::$actions);
    $this->assertSame('rest_api_init', WpStubs::$actions[0]['hook']);

    (WpStubs::$actions[0]['callback'])();

    $this->assertCount(1, WpStubs::$restRoutes);
    $this->assertSame('cloakwp/v1', WpStubs::$restRoutes[0]['namespace']);
    $this->assertSame('/menus', WpStubs::$restRoutes[0]['route']);
    $this->assertSame('GET', WpStubs::$restRoutes[0]['args']['methods']);
    $this->assertArrayHasKey('permission_callback', WpStubs::$restRoutes[0]['args']);
  }

  public function testRegistrationAndRouteForwardingAreIdempotent(): void
  {
    $api = RestApi::make('cloakwp')
      ->routes([Route::get('/menus', static fn(): array => [])->public()]);

    $api->register();
    $api->register();

    $this->assertCount(1, WpStubs::$actions);

    $callback = WpStubs::$actions[0]['callback'];
    $callback();
    $callback();

    $this->assertCount(1, WpStubs::$restRoutes);
  }

  public function testRegistrationRequiresExplicitPermissions(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('requires public() or permission()');

    RestApi::make('cloakwp')
      ->routes([Route::get('/private-by-accident', static fn(): null => null)])
      ->register();
  }

  public function testRejectsInvalidNamespace(): void
  {
    $this->expectException(InvalidArgumentException::class);

    RestApi::make('/cloakwp/');
  }

  public function testRejectsInvalidRoutePath(): void
  {
    $this->expectException(InvalidArgumentException::class);

    Route::get('menus', static fn(): null => null);
  }

  public function testRejectsEmptyMethodList(): void
  {
    $this->expectException(InvalidArgumentException::class);

    Route::make([], '/menus', static fn(): null => null);
  }

  public function testRejectsInvalidArgumentConfiguration(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('"location"');

    Route::get('/menus', static fn(): null => null)->args([
      'location' => 'required',
    ]);
  }

  public function testRejectsNonRouteEntries(): void
  {
    $this->expectException(InvalidArgumentException::class);

    RestApi::make('cloakwp')->routes(['not-a-route']);
  }
}
