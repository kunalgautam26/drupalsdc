<?php

declare(strict_types=1);

namespace Drupal\vite;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\vite\Exception\ManifestCouldNotBeLoadedException;
use Drupal\vite\Exception\ManifestNotFoundException;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Drupal library vite config.
 */
class AssetLibrary {

  use StringTranslationTrait;

  const DEFAULT_VITE_DEV_SERVER_URL = 'http://localhost:5173';

  /**
   * Manifest path relative to the vite dist directory.
   */
  const MANIFEST_PATH = '.vite/manifest.json';

  const DEFAULT_DIST_PATH = 'dist';

  /**
   * Constructs AssetLibrary object.
   */
  public function __construct(
    protected string $libraryId,
    protected array $library,
    protected string $extension,
    protected MessengerInterface $messenger,
    protected LoggerInterface $logger,
    protected ThemeExtensionList $themes,
    protected ModuleExtensionList $modules,
    protected ClientInterface $httpClient,
    TranslationInterface $stringTranslation,
    protected string $appRoot,
    protected bool $isSdc = FALSE,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Returns drupal asset library definition.
   */
  public function getDefinition(): array {
    return $this->library;
  }

  /**
   * Returns vite manifest.
   */
  public function getViteManifest(): ?Manifest {

    // Skip loading manifest for internal dev mode only libraries.
    if ($this->extension === 'vite') {
      return NULL;
    }

    $manifestPath = $this->getManifestPath();

    try {
      return new Manifest($manifestPath);
    }
    catch (ManifestNotFoundException | ManifestCouldNotBeLoadedException $e) {
      $args = [
        '@extension' => $this->extension,
        '@library' => $this->libraryId,
        '@path_to_manifest' => $this->getManifestPath(),
      ];
      // @todo Update error message to add reference to remove reference to deprecated manifest setting.
      $this->messenger->addError($this->t("Could not load vite manifest for the library `@extension/@library` (@path_to_manifest). Perhaps you forgot to build frontend assets, try running `vite build` in the `@extension` theme/module. Also ensure that vite is configured to output manifest to `dist/manifest.json` in the theme/module main directory or that a different path is correctly set in `@extension.libraries.yml` in `@library.vite.manifest`.", $args));
      $this->logger->error("Could not load vite manifest for the library `@extension/@library` (@path_to_manifest). Perhaps you forgot to build frontend assets, try running `vite build` in the `@extension` theme/module. Also ensure that vite is configured to output manifest to `dist/manifest.json` in the theme/module main directory or that a different path is correctly set in `@extension.libraries.yml` in `@library.vite.manifest`.", $args);
    }
    return NULL;
  }

  /**
   * Returns base url used in rewriting library for dist.
   */
  public function getBaseUrl(): ?string {
    return $this->resolveViteSetting('baseUrl');
  }

  /**
   * Returns absolute vite manifest path.
   */
  public function getManifestPath(): string {
    $distDir = $this->getDistDir();
    return $distDir . '/' . self::MANIFEST_PATH;
  }

  /**
   * Returns library extension (module/theme) base path.
   */
  public function getExtensionBasePath(): string {
    if ($this->themes->exists($this->extension)) {
      return $this->themes->getPath($this->extension);
    }
    elseif ($this->modules->exists($this->extension)) {
      return $this->modules->getPath($this->extension);
    }
    throw new \Exception('Could not find library extension (module/theme) base path.');
  }

  /**
   * Checks if library should be managed by vite.
   */
  public function shouldBeManagedByVite(): bool {
    $libraryTypeSpecificSetting = $this->isSdc() ? 'enableInAllComponents' : 'enableInAllLibraries';

    $enabled = FALSE;

    // Check global defaults for all libraries.
    $globalDefault = $this->getDefaultFromSettings('enabled');
    if (is_bool($globalDefault)) {
      $enabled = $globalDefault;
    }

    // Check global defaults for library type.
    $globalDefaultForLibraryType = $this->getDefaultFromSettings($libraryTypeSpecificSetting);
    if (is_bool($globalDefaultForLibraryType)) {
      $enabled = $globalDefaultForLibraryType;
    }

    // Check extension defaults for all libraries.
    $extensionDefault = $this->getViteSettingFromExtensionDefinition('enabled');
    if (is_bool($extensionDefault)) {
      $enabled = $extensionDefault;
    }

    // Check extension defaults for library type.
    $extensionDefaultForLibraryType = $this->getViteSettingFromExtensionDefinition($libraryTypeSpecificSetting);
    if (is_bool($extensionDefaultForLibraryType)) {
      $enabled = $extensionDefaultForLibraryType;
    }

    // Check library specific settings.
    $enabledInLibraryDefinition = $this->getViteSettingFromLibraryConfig('enabled');
    if (is_bool($enabledInLibraryDefinition)) {
      $enabled = $enabledInLibraryDefinition;
    }

    // Check overrides.
    $override = $this->getOverrideFromSettings('enabled');
    if (is_bool($override)) {
      $enabled = $override;
    }

    return $enabled;
  }

  /**
   * Returns vite setting from extension definition.
   */
  private function getViteSettingFromExtensionDefinition(string $setting): mixed {
    // Get extension definition.
    $extensionDefinition = [];
    if ($this->modules->exists($this->extension)) {
      $extensionDefinition = $this->modules->getExtensionInfo($this->extension);
    }
    elseif ($this->themes->exists($this->extension)) {
      $extensionDefinition = $this->themes->getExtensionInfo($this->extension);
    }

    // Check if extension definition has vite settings.
    if (!isset($extensionDefinition['vite'])) {
      return NULL;
    }
    // Special case for handling `vite: true/false`.
    if ($setting === 'enabled' && is_bool($extensionDefinition['vite'])) {
      return $extensionDefinition['vite'];
    }
    // Check if setting is present in extension definition.
    if (!is_array($extensionDefinition['vite']) || !isset($extensionDefinition['vite'][$setting])) {
      return NULL;
    }

    // Return setting value.
    return $extensionDefinition['vite'][$setting];
  }

  /**
   * Returns dev dependencies.
   */
  public function getDevDependencies(): array {
    return $this->library['vite']['devDependencies'] ?? [];
  }

  /**
   * Determines if vite dev server or dist build should serve library assets.
   */
  public function shouldUseDevServer(): bool {
    $useDevServer = $this->resolveViteSetting('useDevServer');
    if ($useDevServer === NULL || $useDevServer === 'auto') {
      try {
        $acceptableStatuses = [
          'vite_4' => 404,
          'vite_5' => 200,
        ];
        $statusCode = $this->httpClient->request('GET', $this->getDevServerBaseUrl(), ['http_errors' => FALSE])->getStatusCode();
        return in_array($statusCode, $acceptableStatuses, TRUE);
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }
    if (is_bool($useDevServer)) {
      return $useDevServer;
    }
    return FALSE;
  }

  /**
   * Returns base url of vite dev server for the library.
   */
  public function getDevServerBaseUrl(): string {
    // Get configured value.
    $devServerUrl = $this->resolveViteSetting('devServerUrl');
    // Or fallback to hardcoded value.
    if (!is_string($devServerUrl) || !UrlHelper::isValid($devServerUrl)) {
      $devServerUrl = self::DEFAULT_VITE_DEV_SERVER_URL;
    }
    return $devServerUrl;
  }

  /**
   * Returns library extension (theme/module) id.
   */
  public function getExtension(): string {
    return $this->extension;
  }

  /**
   * Get vite setting defaults from site settings.
   */
  private function getDefaultFromSettings(string $setting): mixed {
    $settings = $this->getViteSettings();
    $value = NULL;

    // Global defaults.
    if (isset($settings[$setting])) {
      $value = $settings[$setting];
    }

    return $value;
  }

  /**
   * Get vite setting override from site settings.
   */
  private function getOverrideFromSettings(string $setting): mixed {
    $settings = $this->getViteSettings();
    $value = NULL;

    // Extension specific overrides.
    if (isset($settings['overrides'][$this->extension][$setting])) {
      $value = $settings['overrides'][$this->extension][$setting];
    }

    // Library specific overrides.
    if (isset($settings['overrides'][$this->extension . '/' . $this->libraryId][$setting])) {
      $value = $settings['overrides'][$this->extension . '/' . $this->libraryId][$setting];
    }

    return $value;
  }

  /**
   * Get vite settings from site settings.
   */
  private function getViteSettings(): array {
    $settings = Settings::get('vite', []);
    if (!is_array($settings)) {
      return [];
    }

    return $settings;
  }

  /**
   * Resolve vite settings from defaults, library config and overrides.
   */
  private function resolveViteSetting(string $setting): mixed {
    // Global defaults.
    $value = $this->getDefaultFromSettings($setting);

    // Extension definition overrides.
    $extensionOverride = $this->getViteSettingFromExtensionDefinition($setting);
    if (!is_null($extensionOverride)) {
      $value = $extensionOverride;
    }

    // Library definition overrides.
    $libraryOverride = $this->getViteSettingFromLibraryConfig($setting);
    if (!is_null($libraryOverride)) {
      $value = $libraryOverride;
    }

    // Overrides.
    $override = $this->getOverrideFromSettings($setting);
    if (!is_null($override)) {
      $value = $override;
    }

    return $value;
  }

  /**
   * Returns vite library config for the library or NULL.
   */
  private function getViteSettingFromLibraryConfig(string $setting): mixed {
    // Check if library has vite settings.
    if (!isset($this->library['vite'])) {
      return NULL;
    }
    // Special case for handling `vite: true/false`.
    if ($setting === 'enabled' && is_bool($this->library['vite'])) {
      return $this->library['vite'];
    }
    // Check if setting is present in library config.
    if (!is_array($this->library['vite']) || !isset($this->library['vite'][$setting])) {
      return NULL;
    }
    // Return setting value.
    return $this->library['vite'][$setting];
  }

  /**
   * Checks if library is SDC component library.
   */
  public function isSdc(): bool {
    return $this->isSdc;
  }

  /**
   * Get vite root path.
   */
  public function getViteRoot(): string {
    // Get configured value.
    $viteRoot = $this->resolveViteSetting('viteRoot');
    // Fallback to extension root.
    if (!is_string($viteRoot)) {
      $viteRoot = '.';
    }

    // Resolve vite root path relative to the app root.
    if ($viteRoot === '.') {
      $viteRootPath = $this->getExtensionBasePath();
    }
    elseif (str_starts_with($viteRoot, '/')) {
      $viteRootPath = $viteRoot;
    }
    else {
      $viteRootPath = $this->getExtensionBasePath() . '/' . $viteRoot;
    }

    $viteRootPath = ltrim($viteRootPath, '/');
    $realViteRootPath = Vite::getAbsolutePath($this->appRoot . '/' . $viteRootPath);
    if (!is_string($realViteRootPath)) {
      throw new \Exception('Could not resolve vite root path.');
    }

    return $realViteRootPath;
  }

  /**
   * Get absolute vite dist directory path.
   */
  public function getDistDir(): string {
    $distDirPath = $this->resolveViteSetting('distDir');

    // If dist path is not explicitly set, try to resolve it based on deprecated manifest path setting.
    $manifestPath = $this->resolveViteSetting('manifest');
    if (is_null($distDirPath) && is_string($manifestPath)) {
      $args = ['@library' => $this->extension . '/' . $this->libraryId];
      $this->messenger->addWarning($this->t("Vite module 'manifest' config option is deprecated. Use 'distDir' instead. (@library)", $args));
      $this->logger->warning("Vite module 'manifest' config option is deprecated. Use 'distDir' instead. (@library)", $args);
      $realManifestPath = Vite::getAbsolutePath(
        $this->getViteRoot() . '/' . ltrim($manifestPath, '/')
      );
      if (is_string($realManifestPath)) {
        $distDotVitePath = dirname($realManifestPath);
        $distDirPath = Vite::getAbsolutePath($distDotVitePath . '/..');
        if (is_string($distDirPath)) {
          return $distDirPath;
        }
      }
    }

    // Fallback to default dist path.
    $distDirPath = $distDirPath ?? self::DEFAULT_DIST_PATH;
    // Normalize dist path.
    $distDirPath = trim($distDirPath, '/');

    $absoluteDistDirPath = Vite::getAbsolutePath($this->getViteRoot() . '/' . $distDirPath);
    // If configured dist path is not valid, fallback to default.
    if (!is_string($absoluteDistDirPath)) {
      $absoluteDistDirPath = $this->appRoot . '/' . $this->getExtensionBasePath() . '/' . self::DEFAULT_DIST_PATH;
    }
    return $absoluteDistDirPath;
  }

}
