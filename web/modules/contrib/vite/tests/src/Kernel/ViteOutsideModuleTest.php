<?php

namespace Drupal\Tests\vite\Kernel;

use Drupal\Tests\vite\ViteKernelTestBase;
use Drupal\vite\Vite;

/**
 * Tests for Vite module.
 *
 * @group vite
 */
class ViteOutsideModuleTest extends ViteKernelTestBase {

  protected const TEST_EXTENSION = 'test_module_vite5_outside_module_root';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    self::TEST_EXTENSION,
  ];

  public function testModuleCssAssetPath(): void {
    $this->assertLibraryCssAssetPath(
      '/../dist/assets/styles-cUFdXfqL.css',
      $this->getLibraryDefinition('test_library'),
    );
  }

  public function testModuleJsAssetPath(): void {
    $this->assertLibraryJsAssetPath(
      '/../dist/assets/script-N0e6cqTp.js',
      $this->getLibraryDefinition('test_library'),
    );
  }

  public function testComponentCssAssetPath(): void {
    $this->assertLibraryCssAssetPath(
      '/dist/assets/button-Dtum58Th.css',
      $this->getComponentLibraryDefinition('button'),
      isSDC: TRUE,
      viteRoot: Vite::getAbsolutePath($this->moduleBasePath . '/..'),
    );
  }

  public function testComponentJsAssetPath(): void {
    $this->assertLibraryJsAssetPath(
      '/dist/assets/button-FEBBvLX1.js',
      $this->getComponentLibraryDefinition('button'),
      isSDC: TRUE,
      viteRoot: Vite::getAbsolutePath($this->moduleBasePath . '/..'),
    );
  }

}
