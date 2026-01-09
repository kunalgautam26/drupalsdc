<?php

namespace Drupal\Tests\vite\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test loading imports only once.
 *
 * @group vite
 */
class ViteImportLoadingTest extends WebDriverTestBase {

  protected const TEST_EXTENSION = 'test_module_vite5';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    self::TEST_EXTENSION,
  ];

  /**
   * Test the import behaviour.
   */
  public function testScriptImports(): void {
    $this->drupalGet('<front>');

    $count = $this->getSession()->evaluateScript("sessionStorage.getItem('import_test_counter') || 0");
    static::assertEquals(1, $count);
  }

}
