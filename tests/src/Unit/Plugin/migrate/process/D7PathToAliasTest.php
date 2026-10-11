<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Unit\Plugin\migrate\process;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\d7_to_d11_migrations\Plugin\migrate\process\D7PathToAlias;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the D7PathToAlias process plugin.
 *
 * External schemes are decided before any migration lookup, so these cases
 * do not boot a Drupal container.
 */
#[Group('d7_to_d11_migrations')]
#[CoversClass(D7PathToAlias::class)]
final class D7PathToAliasTest extends TestCase {

  /**
   * Runs a source path through a freshly configured D7PathToAlias instance.
   *
   * @param mixed $value
   *   The source path or URL.
   *
   * @return string
   *   The destination URI.
   */
  private function transform(mixed $value): string {
    $manager = $this->createMock(PluginManagerInterface::class);
    $plugin = new D7PathToAlias([], 'd7_to_d11_path_to_alias', [], $manager);
    $executable = $this->createMock(MigrateExecutableInterface::class);
    return $plugin->transform($value, $executable, new Row(), 'redirect');
  }

  /**
   * Tests that a mailto link is not rewritten as an internal path.
   */
  public function testMailtoIsLeftAlone(): void {
    self::assertSame('mailto:hi@example.com', $this->transform('mailto:hi@example.com'));
  }

  /**
   * Tests that a tel link is not rewritten as an internal path.
   */
  public function testTelIsLeftAlone(): void {
    self::assertSame('tel:+1234567890', $this->transform('tel:+1234567890'));
  }

  /**
   * Tests that the scheme check is case insensitive.
   */
  public function testMailtoSchemeIsCaseInsensitive(): void {
    self::assertSame('MAILTO:hi@example.com', $this->transform('MAILTO:hi@example.com'));
  }

  /**
   * Tests that an https URL is left unchanged.
   */
  public function testHttpsIsLeftAlone(): void {
    self::assertSame('https://example.com/a', $this->transform('https://example.com/a'));
  }

  /**
   * Tests that a bare path still receives the internal prefix.
   */
  public function testBarePathBecomesInternal(): void {
    self::assertSame('internal:/about', $this->transform('about'));
  }

  /**
   * Tests that an empty value stays empty.
   */
  public function testEmptyStringStaysEmpty(): void {
    self::assertSame('', $this->transform(''));
  }

}
