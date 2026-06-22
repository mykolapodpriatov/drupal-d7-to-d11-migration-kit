<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Kernel\Process;

use Drupal\d7_to_d11_migrations\Plugin\migrate\process\SplitFullName;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Tests the SplitFullName process plugin.
 *
 * @group d7_to_d11_migrations
 *
 * @coversDefaultClass \Drupal\d7_to_d11_migrations\Plugin\migrate\process\SplitFullName
 */
final class SplitFullNameTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'd7_to_d11_migrations'];

  /**
   * Provides full-name strings with their expected first and last parts.
   *
   * @return iterable<string, array{0: string, 1: string, 2: string}>
   *   value, first, last
   */
  public static function fullNameProvider(): iterable {
    yield 'empty string'              => ['', '', ''];
    yield 'whitespace only'           => ['   ', '', ''];
    yield 'single token'              => ['Cher', 'Cher', ''];
    yield 'two tokens'                => ['Ada Lovelace', 'Ada', 'Lovelace'];
    yield 'three tokens'              => ['Jean Paul Sartre', 'Jean Paul', 'Sartre'];
    yield 'multibyte cyrillic'        => ['Анна Каренина', 'Анна', 'Каренина'];
    yield 'multibyte three parts'     => ['Лев Николаевич Толстой', 'Лев Николаевич', 'Толстой'];
    yield 'extra inner spaces'        => ['Mary  Shelley', 'Mary', 'Shelley'];
    yield 'trailing whitespace'       => ['Ada Lovelace ', 'Ada', 'Lovelace'];
    yield 'hyphenated last name'      => ['Anne-Marie de Vries', 'Anne-Marie de', 'Vries'];
  }

  /**
   * Tests splitting full names into first and last parts.
   *
   * @dataProvider fullNameProvider
   * @covers ::transform
   */
  public function testTransform(string $value, string $expected_first, string $expected_last): void {
    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);

    $first_plugin = new SplitFullName(['part' => 'first'], 'd7_to_d11_split_full_name', []);
    self::assertSame($expected_first, $first_plugin->transform($value, $executable, $row, 'field_first_name'));

    $last_plugin = new SplitFullName(['part' => 'last'], 'd7_to_d11_split_full_name', []);
    self::assertSame($expected_last, $last_plugin->transform($value, $executable, $row, 'field_last_name'));
  }

  /**
   * @covers ::transform
   */
  public function testInvalidPartFallsBackToFirst(): void {
    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);

    $plugin = new SplitFullName(['part' => 'middle'], 'd7_to_d11_split_full_name', []);
    self::assertSame('Ada', $plugin->transform('Ada Lovelace', $executable, $row, 'field_first_name'));
  }

  /**
   * @covers ::transform
   */
  public function testNonStringValuesReturnEmpty(): void {
    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);

    $plugin = new SplitFullName(['part' => 'first'], 'd7_to_d11_split_full_name', []);
    self::assertSame('', $plugin->transform(NULL, $executable, $row, 'field_first_name'));
    self::assertSame('', $plugin->transform(12345, $executable, $row, 'field_first_name'));
    self::assertSame('', $plugin->transform(['Ada', 'Lovelace'], $executable, $row, 'field_first_name'));
  }

}
