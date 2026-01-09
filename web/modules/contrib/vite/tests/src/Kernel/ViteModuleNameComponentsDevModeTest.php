<?php

namespace Drupal\Tests\vite\Kernel;

use Drupal\Tests\vite\ViteKernelTestBase;

/**
 * Tests for Vite module.
 *
 * @group vite
 */
class ViteModuleNameComponentsDevModeTest extends ViteKernelTestBase {

  protected const TEST_EXTENSION = 'test_module_vite5_components';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    self::TEST_EXTENSION,
  ];

  protected function setUp(): void {
    parent::setUp();
    // Switch do dev mode.
    $this->setSetting('vite', [
      'useDevServer' => TRUE,
    ]);
    $this->clearDiscoveryCache();
    // In dev mode module base path is not used.
    $this->moduleBasePath = '';
  }

  public function testModuleCssAssetPathInDevMode(): void {
    $this->assertLibraryJsAssetPath(
      'http://localhost:5173/scss/styles.scss',
      $this->getLibraryDefinition('test_library'),
    );
  }

  public function testModuleJsAssetPathInDevMode(): void {
    $this->assertLibraryJsAssetPath(
      'http://localhost:5173/js/script.js',
      $this->getLibraryDefinition('test_library'),
      assetIndex: 1,
    );
  }

  public function testComponentCssAssetPathInDevMode(): void {
    $this->assertLibraryJsAssetPath(
      'http://localhost:5173/components/button/button.css',
      $this->getComponentLibraryDefinition('button'),
    );
  }

  public function testComponentJsAssetPathInDevMode(): void {
    $this->assertLibraryJsAssetPath(
      'http://localhost:5173/components/button/button.js',
      $this->getComponentLibraryDefinition('button'),
      assetIndex: 1,
    );
  }

}
