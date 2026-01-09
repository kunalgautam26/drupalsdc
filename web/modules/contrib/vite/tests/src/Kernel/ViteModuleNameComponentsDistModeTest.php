<?php

namespace Drupal\Tests\vite\Kernel;

use Drupal\Tests\vite\ViteKernelTestBase;

/**
 * Tests for Vite module.
 *
 * @group vite
 */
class ViteModuleNameComponentsDistModeTest extends ViteKernelTestBase {

  protected const TEST_EXTENSION = 'test_module_vite5_components';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    self::TEST_EXTENSION,
  ];

  public function testModuleCssAssetPathInDistMode(): void {
    $this->assertLibraryCssAssetPath(
      '/dist/assets/styles-Ke2QOyja.css',
      $this->getLibraryDefinition('test_library'),
    );
  }

  public function testModuleJsAssetPathInDistMode(): void {
    $this->assertLibraryJsAssetPath(
      '/dist/assets/script-N0e6cqTp.js',
      $this->getLibraryDefinition('test_library'),
    );
  }

  public function testComponentCssAssetPathInDistMode(): void {
    $this->assertLibraryCssAssetPath(
      '/dist/assets/button-D8pfZ1QP.css',
      $this->getComponentLibraryDefinition('button'),
      isSDC: TRUE,
    );
  }

  public function testComponentJsAssetPathInDistMode(): void {
    $this->assertLibraryJsAssetPath(
      '/dist/assets/button-FEBBvLX1.js',
      $this->getComponentLibraryDefinition('button'),
      isSDC: TRUE,
    );
  }

}
