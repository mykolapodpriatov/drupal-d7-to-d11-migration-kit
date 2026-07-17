<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Unit\Plugin\migrate\process;

use Drupal\d7_to_d11_migrations\Plugin\migrate\process\D7RoleMap;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the D7RoleMap process plugin.
 *
 * The plugin extends the migrate ProcessPluginBase, but its transform() only
 * reads plugin configuration, so it is exercised here as a pure unit test with
 * no Drupal container.
 */
#[Group('d7_to_d11_migrations')]
#[CoversClass(D7RoleMap::class)]
final class D7RoleMapTest extends TestCase {

  /**
   * The D7 rid to D11 machine-name map shared across the test cases.
   *
   * @var array<int, string>
   */
  private const MAP = [
    2 => 'authenticated',
    3 => 'administrator',
    4 => 'editor',
  ];

  /**
   * Runs a source value through a freshly configured D7RoleMap instance.
   *
   * @param array<string, mixed> $configuration
   *   The process plugin configuration.
   * @param mixed $value
   *   The source value (D7 role data).
   *
   * @return array<int, string>
   *   The mapped D11 role machine names.
   */
  private function transform(array $configuration, mixed $value): array {
    $plugin = new D7RoleMap($configuration, 'd7_to_d11_role_map', []);
    $executable = $this->createMock(MigrateExecutableInterface::class);
    return $plugin->transform($value, $executable, new Row(), 'roles');
  }

  /**
   * Tests that known rids map to their configured machine names.
   */
  public function testMapsKnownRidsToMachineNames(): void {
    $config = ['map' => self::MAP, 'default_value' => 'authenticated'];
    // D7 role data arrives as an array keyed by rid.
    $value = [2 => 'authenticated user', 3 => 'administrator'];
    $expected = ['authenticated', 'administrator'];
    self::assertSame($expected, $this->transform($config, $value));
  }

  /**
   * Tests that an unmapped rid falls back to the configured default.
   */
  public function testUnmappedRidFallsBackToDefault(): void {
    $config = ['map' => self::MAP, 'default_value' => 'authenticated'];
    // Role id 7 is absent from the map, so the default machine name is used.
    self::assertSame(['authenticated'], $this->transform($config, [7 => 'x']));
  }

  /**
   * Tests that an unmapped rid is dropped when no default is configured.
   */
  public function testUnmappedRidWithoutDefaultIsDropped(): void {
    $config = ['map' => self::MAP];
    self::assertSame([], $this->transform($config, [7 => 'x']));
  }

  /**
   * Tests that a scalar rid is accepted as well as an array.
   */
  public function testScalarRidIsAccepted(): void {
    $config = ['map' => self::MAP, 'default_value' => 'authenticated'];
    self::assertSame(['editor'], $this->transform($config, 4));
  }

  /**
   * Tests that repeated machine names collapse to a unique list.
   */
  public function testDeduplicatesRepeatedMachineNames(): void {
    $config = ['map' => self::MAP, 'default_value' => 'authenticated'];
    // Two unmapped rids both fall back to the same default and collapse.
    $value = [7 => 'a', 8 => 'b'];
    self::assertSame(['authenticated'], $this->transform($config, $value));
  }

  /**
   * Tests that a missing map configuration raises a MigrateException.
   */
  public function testMissingMapConfigurationThrows(): void {
    $this->expectException(MigrateException::class);
    $this->transform(['default_value' => 'authenticated'], [2 => 'x']);
  }

}
