<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Kernel\Process;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\d7_to_d11_migrations\Plugin\migrate\process\RewriteMediaEmbeds;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Tests the RewriteMediaEmbeds process plugin.
 *
 * @group d7_to_d11_migrations
 *
 * @coversDefaultClass \Drupal\d7_to_d11_migrations\Plugin\migrate\process\RewriteMediaEmbeds
 */
final class RewriteMediaEmbedsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'd7_to_d11_migrations'];

  /**
   * @covers ::transform
   */
  public function testJsonMediaTokenIsRewritten(): void {
    $plugin = $this->buildPluginWithFidMap([42 => 'aaaa-bbbb-cccc-dddd']);

    $input = 'Before [[{"type":"media","fid":"42","attributes":{}}]] After';
    $expected = 'Before <drupal-media data-entity-type="media" data-entity-uuid="aaaa-bbbb-cccc-dddd"></drupal-media> After';

    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);
    self::assertSame($expected, $plugin->transform($input, $executable, $row, 'body/value'));
  }

  /**
   * @covers ::transform
   */
  public function testUnknownFidIsLeftAlone(): void {
    $plugin = $this->buildPluginWithFidMap([]);

    $input = 'X [[{"type":"media","fid":"999"}]] Y';
    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);
    self::assertSame($input, $plugin->transform($input, $executable, $row, 'body/value'));
  }

  /**
   * @covers ::transform
   */
  public function testEmptyAndNonStringInput(): void {
    $plugin = $this->buildPluginWithFidMap([]);
    $row = new Row();
    $executable = $this->createMock(MigrateExecutableInterface::class);

    self::assertSame('', $plugin->transform('', $executable, $row, 'body/value'));
    self::assertSame('', $plugin->transform(NULL, $executable, $row, 'body/value'));
  }

  /**
   * Builds a plugin instance with mocked migration_lookup + media storage.
   *
   * @param array<int, string> $fid_to_uuid
   *   Source fid => destination media UUID.
   */
  private function buildPluginWithFidMap(array $fid_to_uuid): RewriteMediaEmbeds {
    $id_map = $this->createMock(MigrateIdMapInterface::class);
    $id_map->method('lookupDestinationIds')->willReturnCallback(
      function (array $source) use ($fid_to_uuid): array {
        $fid = (int) ($source['fid'] ?? 0);
        return isset($fid_to_uuid[$fid]) ? [[$fid]] : [];
      },
    );

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getIdMap')->willReturn($id_map);

    $migration_manager = $this->createMock(PluginManagerInterface::class);
    $migration_manager->method('createInstance')->willReturn($migration);

    $entities = [];
    foreach ($fid_to_uuid as $fid => $uuid) {
      $entity = $this->createMock(EntityInterface::class);
      $entity->method('uuid')->willReturn($uuid);
      $entities[$fid] = $entity;
    }

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(
      fn (int|string $id): ?EntityInterface => $entities[(int) $id] ?? NULL,
    );

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('media')->willReturn($storage);

    return new RewriteMediaEmbeds(
      ['media_migration' => 'd7_file_to_media'],
      'd7_to_d11_rewrite_media_embeds',
      [],
      $etm,
      $migration_manager,
    );
  }

}
