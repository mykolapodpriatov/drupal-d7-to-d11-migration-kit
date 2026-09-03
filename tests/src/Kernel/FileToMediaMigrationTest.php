<?php

declare(strict_types=1);

namespace Drupal\Tests\d7_to_d11_migrations\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileExists;
use Drupal\Core\StreamWrapper\PrivateStream;
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
   *
   * The `stream_wrapper.private` service is normally only registered by
   * \Drupal\Core\CoreServiceProvider when the `file_private_path` setting is
   * already present at container-build time. That setting is set later, in
   * setUp() below, via KernelTestBase::setSetting() (which updates the
   * in-memory \Drupal\Core\Site\Settings singleton without rebuilding the
   * container) — so without this override the `private://` stream wrapper
   * would never resolve. This mirrors core's own
   * \Drupal\Tests\file\Kernel\Migrate\d7\MigratePrivateFileTest.
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', PrivateStream::class)
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

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

    // The shared migrate_drupal D7 fixture owns every file_managed row used
    // here by uid 1. d7_users.yml deliberately refuses to migrate — or stub —
    // D7 uid 1 (it maps to D11's own uid 1 administrator instead), so the
    // `uid` migration_lookup on d7_files.yml would throw a
    // MigrateSkipRowException while trying to stub it, silently skipping
    // every file row. Re-point ownership at uid 2 (a real, non-excluded user
    // in the same fixture) so the file migration can stub a d7_users row
    // normally, same as it would for any real D7 site whose files aren't all
    // owned by the site administrator.
    Database::getConnection('default', 'migrate_d7')
      ->update('file_managed')
      ->fields(['uid' => 2])
      ->execute();
  }

  /**
   * Tests that image files become media with a resolvable UUID.
   *
   * Also exercises the `private://` branch of `d7_to_d11_ensure_file_public`:
   * fid 3 in the shared migrate_drupal D7 fixture (`Babylon5.txt`) is a
   * `private://` file, and asserting on it here ensures a regression in the
   * private-scheme handling — or in the `stream_wrapper.private` test
   * registration above — fails this test instead of silently no-op'ing.
   */
  public function testImageFileBecomesMediaWithUuid(): void {
    $this->startCollectingMessages();
    $this->executeMigration('d7_files');
    $this->executeMigration('d7_file_to_media');

    $file = File::load(1);
    $this->assertInstanceOf(File::class, $file);
    $this->assertSame('cube.jpeg', $file->getFilename());

    $private_file = File::load(3);
    $this->assertInstanceOf(File::class, $private_file);
    $this->assertSame('Babylon5.txt', $private_file->getFilename());
    $this->assertSame('private://Babylon5.txt', $private_file->getFileUri());
    $this->assertFileExists($private_file->getFileUri());

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
