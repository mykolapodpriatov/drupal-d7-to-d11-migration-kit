<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Unit\Helper;

use Drupal\d7_to_d11_migrations\Helper\LinkConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the LinkConverter helper.
 *
 * Pure unit test: LinkConverter is static, dependency-free PHP, so no Drupal
 * bootstrap is required.
 */
#[Group('d7_to_d11_migrations')]
#[CoversClass(LinkConverter::class)]
final class LinkConverterTest extends TestCase {

  /**
   * Provides D7 link values with their expected D11 uri conversion.
   *
   * @return iterable<string, array{0: string|null, 1: string}>
   *   The raw D7 URL and the expected D11 link-field uri string.
   */
  public static function urlProvider(): iterable {
    yield 'null input' => [NULL, 'internal:/'];
    yield 'empty string' => ['', 'internal:/'];
    yield 'whitespace only' => ['   ', 'internal:/'];
    yield 'front token' => ['<front>', 'internal:/'];
    yield 'external https' => [
      'https://example.com/page',
      'https://example.com/page',
    ];
    yield 'external http' => ['http://example.com', 'http://example.com'];
    yield 'mailto' => ['mailto:hi@example.com', 'mailto:hi@example.com'];
    yield 'tel' => ['tel:+1234567890', 'tel:+1234567890'];
    yield 'leading slash path' => ['/about-us', 'internal:/about-us'];
    yield 'bare internal path' => ['node/123', 'internal:/node/123'];
  }

  /**
   * Tests conversion of D7 link URLs into D11 link-field uri values.
   *
   * @param string|null $url
   *   The raw D7 link URL.
   * @param string $expected
   *   The expected D11 uri string.
   */
  #[DataProvider('urlProvider')]
  public function testToD11Uri(?string $url, string $expected): void {
    self::assertSame($expected, LinkConverter::toD11Uri($url));
  }

}
