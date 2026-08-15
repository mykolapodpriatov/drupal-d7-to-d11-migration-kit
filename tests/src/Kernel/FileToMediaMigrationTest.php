<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileExists;
use Drupal\d7_to_d11_migrations\Plugin\migrate\process\RewriteMediaEmbeds;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the d7_files → d7_file_to_media pipeline.
 *
 * @group d7_to_d11_migrations
 */
#[Group('d7_to_d11_migrations')]
#[RunTestsInSeparateProcesses]
final class FileToMediaMigrationTest extends MigrateDrupal7TestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'media',
    'migrate_plus',
    'd7_to_d11_migrations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('media');
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);
    $this->installConfig(['d7_to_d11_migrations']);

    $this->createMediaType('image', [
      'id' => 'image',
      'label' => 'Image',
    ]);

    // The module's plugin alter / group config pin source.key to migrate_d7.
    $info = Database::getConnectionInfo('migrate');
    Database::addConnectionInfo('migrate_d7', 'default', $info['default']);

    $fs = $this->container->get('file_system');
    $jpeg = $this->root . '/core/tests/fixtures/files/image-2.jpg';
    $fs->copy($jpeg, 'public://cube.jpeg', FileExists::Replace);
    file_put_contents('public://ds9.txt', 'ds9');

    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
    $fs->mkdir($this->siteDirectory . '/private', NULL, TRUE);
    file_put_contents('private://Babylon5.txt', 'B5');
  }

  /**
   * Tests that image files become media with a resolvable UUID.
   */
  public function testImageFileBecomesMediaWithUuid(): void {
    $this->startCollectingMessages();
    $this->executeMigration('d7_files');
    $this->executeMigration('d7_file_to_media');

    $file = File::load(1);
    $this->assertInstanceOf(File::class, $file);
    $this->assertSame('cube.jpeg', $file->getFilename());

    $destination_ids = $this->getMigration('d7_file_to_media')
      ->getIdMap()
      ->lookupDestinationIds(['fid' => 1]);
    $this->assertNotEmpty($destination_ids[0][0]);

    $media = Media::load($destination_ids[0][0]);
    $this->assertInstanceOf(Media::class, $media);
    $this->assertSame('image', $media->bundle());
    $this->assertSame(
      (int) $file->id(),
      (int) $media->get('field_media_image')->target_id,
    );
    $this->assertNotEmpty($media->uuid());

    $images = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->loadByProperties(['bundle' => 'image']);
    $this->assertCount(1, $images);

    $plugin = RewriteMediaEmbeds::create(
      $this->container,
      ['media_migration' => 'd7_file_to_media'],
      'd7_to_d11_rewrite_media_embeds',
      [],
    );
    $input = 'Intro [[{"type":"media","fid":"1"}]] outro';
    $output = $plugin->transform(
      $input,
      $this->createMock(MigrateExecutableInterface::class),
      new Row(),
      'body/value',
    );
    $expected = sprintf(
      'Intro <drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media> outro',
      $media->uuid(),
    );
    $this->assertSame($expected, $output);
  }

}
