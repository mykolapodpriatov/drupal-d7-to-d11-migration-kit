<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Unit\Plugin\migrate\process;

use Drupal\d7_to_d11_migrations\Plugin\migrate\process\StripScheme;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the StripScheme process plugin.
 *
 * The plugin only reads the input value — it is a pure value transform that
 * never touches the row — so it is exercised here as a pure unit test with a
 * real Row and a mocked MigrateExecutableInterface, without booting a Drupal
 * container.
 */
#[Group('d7_to_d11_migrations')]
#[CoversClass(StripScheme::class)]
final class StripSchemeTest extends TestCase {

  /**
   * Runs a source URI through a freshly configured StripScheme instance.
   *
   * @param mixed $value
   *   The source value (a D7 file URI).
   *
   * @return string
   *   The scheme-stripped target.
   */
  private function transform(mixed $value): string {
    $plugin = new StripScheme([], 'd7_to_d11_strip_scheme', []);
    $executable = $this->createMock(MigrateExecutableInterface::class);
    return $plugin->transform($value, $executable, new Row(), 'filepath_without_scheme');
  }

  /**
   * Tests that the scheme is stripped, keeping the relative path.
   */
  public function testStripsPublicScheme(): void {
    self::assertSame('sub/dir/name.txt', $this->transform('public://sub/dir/name.txt'));
  }

  /**
   * Tests that a private:// scheme is stripped the same way.
   */
  public function testStripsPrivateScheme(): void {
    self::assertSame('docs/secret.pdf', $this->transform('private://docs/secret.pdf'));
  }

  /**
   * Tests that a value without a scheme is returned verbatim.
   */
  public function testValueWithoutSchemeIsReturnedUnchanged(): void {
    self::assertSame('sites/default/files/a.txt', $this->transform('sites/default/files/a.txt'));
  }

  /**
   * Tests that an empty string returns ''.
   */
  public function testEmptyStringReturnsEmpty(): void {
    self::assertSame('', $this->transform(''));
  }

  /**
   * Tests that a NULL value returns ''.
   */
  public function testNullValueReturnsEmpty(): void {
    self::assertSame('', $this->transform(NULL));
  }

  /**
   * Tests that a non-string scalar value returns ''.
   */
  public function testNonStringValueReturnsEmpty(): void {
    self::assertSame('', $this->transform(123));
  }

}
