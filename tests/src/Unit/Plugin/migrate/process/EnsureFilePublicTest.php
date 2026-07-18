<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Unit\Plugin\migrate\process;

use Drupal\d7_to_d11_migrations\Plugin\migrate\process\EnsureFilePublic;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EnsureFilePublic process plugin.
 *
 * The plugin only reads its own configuration and mutates the passed Row, so it
 * is exercised here as a pure unit test with a real Row and a mocked
 * MigrateExecutableInterface, without booting a Drupal container.
 */
#[Group('d7_to_d11_migrations')]
#[CoversClass(EnsureFilePublic::class)]
final class EnsureFilePublicTest extends TestCase {

  /**
   * Runs a source URI through a freshly configured EnsureFilePublic instance.
   *
   * @param array<string, mixed> $configuration
   *   The process plugin configuration.
   * @param mixed $value
   *   The source value (a D7 file URI).
   * @param \Drupal\migrate\Row $row
   *   The row whose source properties the plugin may mutate.
   *
   * @return string
   *   The re-routed destination URI.
   */
  private function transform(array $configuration, mixed $value, Row $row): string {
    $plugin = new EnsureFilePublic($configuration, 'd7_to_d11_ensure_file_public', []);
    $executable = $this->createMock(MigrateExecutableInterface::class);
    return $plugin->transform($value, $executable, $row, 'uri');
  }

  /**
   * Tests that a public:// URI keeps the default public destination scheme.
   */
  public function testPublicSchemeMapsToPublicDestination(): void {
    $result = $this->transform([], 'public://images/photo.jpg', new Row());
    self::assertSame('public://images/photo.jpg', $result);
  }

  /**
   * Tests that a private:// URI keeps the default private destination scheme.
   */
  public function testPrivateSchemeMapsToPrivateDestination(): void {
    $result = $this->transform([], 'private://docs/secret.pdf', new Row());
    self::assertSame('private://docs/secret.pdf', $result);
  }

  /**
   * Tests that a temporary:// URI is promoted to the public destination.
   */
  public function testTemporarySchemeMapsToPublicDestination(): void {
    $result = $this->transform([], 'temporary://scratch.txt', new Row());
    self::assertSame('public://scratch.txt', $result);
  }

  /**
   * Tests that an unrecognised scheme is returned verbatim.
   */
  public function testUnknownSchemeIsReturnedUnchanged(): void {
    $result = $this->transform([], 'ftp://host/file.bin', new Row());
    self::assertSame('ftp://host/file.bin', $result);
  }

  /**
   * Tests that a value without a scheme is returned verbatim.
   */
  public function testValueWithoutSchemeIsReturnedUnchanged(): void {
    $result = $this->transform([], 'sites/default/files/a.txt', new Row());
    self::assertSame('sites/default/files/a.txt', $result);
  }

  /**
   * Tests that configuration can re-route public files to a private scheme.
   */
  public function testConfiguredDestinationsReroutePublicToPrivate(): void {
    $config = [
      'public_destination' => 'private://',
      'private_destination' => 'private://',
    ];
    $result = $this->transform($config, 'public://legal/contract.pdf', new Row());
    self::assertSame('private://legal/contract.pdf', $result);
  }

  /**
   * Tests that the scheme-stripped target is exposed on the row.
   */
  public function testSetsFilepathWithoutScheme(): void {
    $row = new Row();
    $this->transform([], 'public://sub/dir/name.txt', $row);
    self::assertSame('sub/dir/name.txt', $row->getSourceProperty('filepath_without_scheme'));
  }

  /**
   * Tests that an empty string returns '' and leaves the row untouched.
   */
  public function testEmptyStringReturnsEmptyWithoutSideEffect(): void {
    $row = new Row();
    self::assertSame('', $this->transform([], '', $row));
    self::assertNull($row->getSourceProperty('filepath_without_scheme'));
  }

  /**
   * Tests that a NULL value returns '' and leaves the row untouched.
   */
  public function testNullValueReturnsEmptyWithoutSideEffect(): void {
    $row = new Row();
    self::assertSame('', $this->transform([], NULL, $row));
    self::assertNull($row->getSourceProperty('filepath_without_scheme'));
  }

  /**
   * Tests that a non-string scalar value returns ''.
   */
  public function testNonStringValueReturnsEmpty(): void {
    self::assertSame('', $this->transform([], 123, new Row()));
  }

}
