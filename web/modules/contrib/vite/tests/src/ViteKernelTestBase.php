<?php

namespace Drupal\Tests\vite;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Vite kernel test base.
 *
 * @group vite
 */
class ViteKernelTestBase extends KernelTestBase {

  protected const TEST_EXTENSION = 'test_module_vite5';

  protected LibraryDiscoveryInterface $libraryDiscovery;

  protected string $moduleBasePath = '';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'vite',
    self::TEST_EXTENSION,
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->libraryDiscovery = \Drupal::service('library.discovery');
    $this->moduleBasePath = \Drupal::service('module_handler')->getModule(static::TEST_EXTENSION)->getPath();
  }

  protected function getLibraryDefinition(string $library): array|false {
    return $this->libraryDiscovery->getLibraryByName(static::TEST_EXTENSION, $library);
  }

  protected function getComponentLibraryDefinition(string $component): array|false {
    return $this->libraryDiscovery->getLibraryByName('core', 'components.' . static::TEST_EXTENSION . '--' . $component);
  }

  protected function assertLibraryAssetPath(
    string $expectedPath,
    array $library,
    string $assetType,
    int $assetIndex = 0,
    bool $isSDC = FALSE,
    ?string $viteRoot = NULL,
  ): void {
    static::assertArrayHasKey($assetType, $library);
    static::assertArrayHasKey($assetIndex, $library[$assetType]);
    static::assertArrayHasKey('data', $library[$assetType][$assetIndex]);
    $base_path = $viteRoot ? trim($viteRoot, '/') : $this->moduleBasePath;
    $prefix = $isSDC ? 'core/../' . $base_path : $base_path;

    static::assertEquals($prefix . $expectedPath, $library[$assetType][$assetIndex]['data']);
  }

  protected function assertLibraryCssAssetPath(
    string $expectedPath,
    array $library,
    int $assetIndex = 0,
    bool $isSDC = FALSE,
    ?string $viteRoot = NULL,
  ): void {
    $this->assertLibraryAssetPath($expectedPath, $library, 'css', $assetIndex, $isSDC, $viteRoot);
  }

  protected function assertLibraryJsAssetPath(
    string $expectedPath,
    array $library,
    int $assetIndex = 0,
    bool $isSDC = FALSE,
    ?string $viteRoot = NULL,
  ): void {
    $this->assertLibraryAssetPath($expectedPath, $library, 'js', $assetIndex, $isSDC, $viteRoot);
  }

  protected function clearDiscoveryCache(): void {
    if ($this->libraryDiscovery instanceof CacheCollectorInterface) {
      $this->libraryDiscovery->clear();
      return;
    }
    // Fallback for Drupal <11.1 (https://www.drupal.org/node/3462970).
    // @phpstan-ignore method.deprecated
    $this->libraryDiscovery->clearCachedDefinitions();
  }

}
