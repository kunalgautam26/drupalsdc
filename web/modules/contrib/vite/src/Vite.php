<?php

declare(strict_types=1);

namespace Drupal\vite;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\vite\Exception\LibraryNotFoundException;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Rewrites libraries to work with vite.
 */
class Vite {

  /**
   * Logger.
   */
  private LoggerInterface $logger;

  /**
   * Constructs the Vite service object.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger_factory,
    protected ThemeExtensionList $themes,
    protected ModuleExtensionList $modules,
    protected LibraryDiscoveryInterface $libraryDiscovery,
    protected ClientInterface $httpClient,
    protected TranslationInterface $stringTranslation,
    protected string $appRoot,
  ) {
    $this->logger = $logger_factory->get('vite');
  }

  /**
   * Process libraries declared to use vite.
   */
  public function processLibraries(array &$libraries, string $extension): void {
    foreach ($libraries as $libraryId => $library) {
      $assetLibrary = $this->getAssetLibrary($extension, $libraryId, $library);
      if (!$assetLibrary->shouldBeManagedByVite()) {
        continue;
      }
      $libraries[$libraryId] = $this->rewriteLibrary($assetLibrary);
      if ($assetLibrary->shouldUseDevServer()) {
        $this->rewriteDevDependencies($libraries, $assetLibrary);
      }
    }
  }

  /**
   * Rewrite the dev dependencies for the given asset library entry.
   *
   * @param array $libraries
   *   The array of libraries to modify.
   * @param AssetLibrary $assetLibrary
   *   The asset library to retrieve dev dependencies and base URL from.
   */
  private function rewriteDevDependencies(array &$libraries, AssetLibrary $assetLibrary): void {
    foreach ($assetLibrary->getDevDependencies() as $fullDependency) {
      // Split the dependency name on slash to remove the module part.
      $dependencyParts = explode('/', $fullDependency);
      $dependency = end($dependencyParts);
      if (isset($libraries[$dependency])) {
        // Modify the library to add an attribute.
        foreach ($libraries[$dependency]['js'] as $path => $options) {
          $libraries[$dependency]['js'][$path]['attributes']['data-vite-dev-server'] = $assetLibrary->getDevServerBaseUrl();
        }
      }
    }
  }

  /**
   * Get the path to a specific chunk.
   */
  public function getChunk(string $extension, string $libraryId, string $chunk): string {
    $library = $this->libraryDiscovery->getLibraryByName($extension, $libraryId);
    if ($library === FALSE) {
      throw new LibraryNotFoundException("Failed loading library: $extension.$libraryId");
    }

    $assetLibrary = $this->getAssetLibrary($extension, $libraryId, $library);
    if (!$assetLibrary->shouldBeManagedByVite()) {
      return $chunk;
    }

    if ($assetLibrary->shouldUseDevServer()) {
      $devServerBaseUrl = $assetLibrary->getDevServerBaseUrl();
      return $devServerBaseUrl . '/' . ltrim($chunk, '/');
    }

    $manifest = $assetLibrary->getViteManifest();
    return $manifest?->getChunk($chunk) ?? $chunk;
  }

  /**
   * Instantiate AssetLibrary object.
   */
  private function getAssetLibrary(string $extension, string $libraryId, array $library): AssetLibrary {
    $libraryExtension = $extension;
    $isSdc = $extension === 'core' && str_starts_with($libraryId, 'components.');
    if ($isSdc) {
      $libraryIdWithoutPrefix = str_replace('components.', '', $libraryId);
      $componentExtension = explode('--', $libraryIdWithoutPrefix)[0];
      $libraryExtension = $componentExtension;
    }

    return new AssetLibrary(
      $libraryId,
      $library,
      $libraryExtension,
      $this->messenger,
      $this->logger,
      $this->themes,
      $this->modules,
      $this->httpClient,
      $this->stringTranslation,
      $this->appRoot,
      $isSdc,
    );
  }

  /**
   * Rewrite library for dev or dist.
   */
  private function rewriteLibrary(AssetLibrary $assetLibrary): array {
    if ($assetLibrary->shouldUseDevServer()) {
      return $this->rewriteLibraryForDev($assetLibrary);
    }
    return $this->rewriteLibraryForDist($assetLibrary);
  }

  /**
   * Rewrite library using dist output.
   */
  private function rewriteLibraryForDist(AssetLibrary $assetLibrary): array {
    $manifest = $assetLibrary->getViteManifest();
    $library = $assetLibrary->getDefinition();

    if ($manifest === NULL) {
      return $library;
    }

    if (isset($library['css'])) {
      foreach ($library['css'] as $type => $paths) {
        foreach ($paths as $originalPath => $options) {
          if (!$this->shouldAssetBeManagedByVite($originalPath, $options)) {
            continue;
          }
          $resolvedPath = $this->resolveSourceAssetPath($originalPath, $assetLibrary);
          $newPath = $manifest->getChunk($resolvedPath);
          if ($newPath === NULL) {
            // Don't rewrite assets not present in the manifest.
            continue;
          }
          $resolvedNewPath = $this->resolveDistAssetPath($newPath, $assetLibrary);
          unset($library['css'][$type][$originalPath]);
          $library['css'][$type][$resolvedNewPath] = $options;

        }
      }
    }

    if (isset($library['js'])) {
      foreach ($library['js'] as $originalPath => $options) {
        if (!$this->shouldAssetBeManagedByVite($originalPath, $options)) {
          continue;
        }
        $resolvedPath = $this->resolveSourceAssetPath($originalPath, $assetLibrary);
        $newPath = $manifest->getChunk($resolvedPath);
        if ($newPath === NULL) {
          // Don't rewrite assets not present in the manifest.
          continue;
        }
        $resolvedNewPath = $this->resolveDistAssetPath($newPath, $assetLibrary);
        unset($library['js'][$originalPath]);

        $attributes = $options['attributes'] ?? [];
        $attributes['type'] = 'module';
        $options['attributes'] = $attributes;
        $library['js'][$resolvedNewPath] = $options;

        $styles = $manifest->getStyles($resolvedPath);
        foreach ($styles as $stylePath) {
          $resolvedStylePath = $this->resolveDistAssetPath($stylePath, $assetLibrary);
          $library['css']['component'][$resolvedStylePath] = [];
        }
      }
    }
    return $library;
  }

  /**
   * Rewrite library to use vite dev server.
   */
  private function rewriteLibraryForDev(AssetLibrary $assetLibrary): array {
    $library = $assetLibrary->getDefinition();

    if ($assetLibrary->getExtension() !== 'vite') {
      $library['dependencies'][] = 'vite/vite-dev-client';
    }

    $devServerBaseUrl = $assetLibrary->getDevServerBaseUrl();
    if (isset($library['css'])) {
      foreach ($library['css'] as $type => $paths) {
        foreach ($paths as $originalPath => $options) {
          if (!$this->shouldAssetBeManagedByVite($originalPath, $options)) {
            continue;
          }
          $resolvedPath = $this->resolveSourceAssetPath($originalPath, $assetLibrary);
          unset($library['css'][$type][$originalPath]);
          $options['type'] = 'external';
          $attributes = $options['attributes'] ?? [];
          $attributes['type'] = 'module';
          $options['attributes'] = $attributes;
          $newPath = $devServerBaseUrl . '/' . ltrim($resolvedPath, '/');
          $library['js'][$newPath] = $options;

        }
      }
    }

    if (isset($library['js'])) {
      foreach ($library['js'] as $originalPath => $options) {
        if (!$this->shouldAssetBeManagedByVite($originalPath, $options)) {
          continue;
        }
        $resolvedPath = $this->resolveSourceAssetPath($originalPath, $assetLibrary);
        unset($library['js'][$originalPath]);
        $options['type'] = 'external';
        $attributes = $options['attributes'] ?? [];
        $attributes['type'] = 'module';
        $options['attributes'] = $attributes;
        $newPath = $devServerBaseUrl . '/' . ltrim($resolvedPath, '/');
        $library['js'][$newPath] = $options;
        if (count($assetLibrary->getDevDependencies()) > 0) {
          $library['dependencies'] = array_merge($library['dependencies'], $assetLibrary->getDevDependencies());
        }
      }

    }
    return $library;
  }

  /**
   * Tries to determine if asset should be managed by vite.
   */
  private function shouldAssetBeManagedByVite(string $path, array $options): bool {
    return $path[0] !== DIRECTORY_SEPARATOR
      && strpos($path, 'http') !== 0
      && (!isset($options['type']) || $options['type'] !== 'external')
      && (!isset($options['vite']) || $options['vite'] !== FALSE)
      && (!isset($options['vite']['enabled']) || $options['vite']['enabled'] !== FALSE);
  }

  /**
   * Resolve source asset path relative to vite root.
   */
  private function resolveSourceAssetPath(string $path, AssetLibrary $assetLibrary): string {
    // Special case for @vite development client.
    if (str_starts_with($path, '@vite')) {
      return $path;
    }
    // Resolve sdc specific paths.
    if ($assetLibrary->isSdc()) {
      $extensionRelativePath = strstr($path, '/components/');
      if ($extensionRelativePath === FALSE) {
        return $path;
      }
      $path = ltrim($extensionRelativePath, '/');
    }

    $viteRoot = $assetLibrary->getViteRoot();
    $absolutePath = static::getAbsolutePath($this->appRoot . '/' . $assetLibrary->getExtensionBasePath() . '/' . $path);

    // Resolve path relative to vite root.
    $resolvedPath = str_replace($viteRoot, '', $absolutePath);
    // Normalize path.
    $resolvedPath = ltrim($resolvedPath, '/');

    return $resolvedPath;
  }

  /**
   * Resolve dist asset path.
   */
  private function resolveDistAssetPath(string $path, AssetLibrary $assetLibrary): string {
    // If base URL is set use it.
    $baseUrl = $assetLibrary->getBaseUrl();
    if (is_string($baseUrl)) {
      return $baseUrl . '/' . ltrim($path, '/');
    }

    // Otherwise resolve path relative to drupal app root.
    $distDir = $assetLibrary->getDistDir();
    $distAssetPath = $distDir . '/' . $path;
    $extensionDir = $this->appRoot . '/' . ($assetLibrary->isSdc() ? 'core' : $assetLibrary->getExtensionBasePath());
    $resolvedDistAssetPath = (new Filesystem())->makePathRelative($distAssetPath, $extensionDir);

    return rtrim($resolvedDistAssetPath, '/');
  }

  /**
   * Resolve relative parts of path to make it absolute.
   */
  public static function getAbsolutePath(string $path): string {
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), fn($val) => strlen($val) > 0);
    $absolutes = [];
    foreach ($parts as $part) {
      if ('.' === $part) {
        continue;
      }
      if ('..' === $part) {
        array_pop($absolutes);
        continue;
      }
      $absolutes[] = $part;
    }
    return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes);
  }

}
