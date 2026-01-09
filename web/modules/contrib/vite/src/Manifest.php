<?php

declare(strict_types=1);

namespace Drupal\vite;

use Drupal\vite\Exception\ManifestCouldNotBeLoadedException;
use Drupal\vite\Exception\ManifestNotFoundException;

/**
 * Object representing vite manifest.
 */
class Manifest {

  /**
   * Vite manifest.
   */
  private array $manifest;

  /**
   * Constructs vite manifest object.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\vite\Exception\ManifestNotFoundException
   * @throws \Drupal\vite\Exception\ManifestCouldNotBeLoadedException
   */
  public function __construct(
    string $manifestPath,
  ) {
    $realManifestPath = Vite::getAbsolutePath($manifestPath);
    if (!file_exists($realManifestPath)) {
      throw new ManifestNotFoundException("Manifest file was not found under path: $manifestPath");
    }

    $manifestContent = file_get_contents($realManifestPath);
    if ($manifestContent === FALSE) {
      throw new ManifestCouldNotBeLoadedException("Failed loading manifest: $manifestPath");
    }

    $manifest = json_decode($manifestContent, TRUE);
    if ($manifest === NULL || !is_array($manifest)) {
      throw new ManifestCouldNotBeLoadedException("Failed loading manifest: $manifestPath");
    }

    $this->manifest = $manifest;
  }

  /**
   * Returns resolved path of given chunk.
   */
  public function getChunk(string $chunk): ?string {
    if (!$this->chunkExists($chunk)) {
      return NULL;
    }
    return $this->manifest[$chunk]['file'];
  }

  /**
   * Returns imports paths of given chunk.
   */
  public function getImports(string $chunk): array {
    return $this->getChunkPropertyPaths('imports', $chunk);
  }

  /**
   * Returns styles paths of given chunk.
   */
  public function getStyles(string $chunk): array {
    if (
      !$this->chunkExists($chunk)
      || !isset($this->manifest[$chunk]['css'])
      || !is_array($this->manifest[$chunk]['css'])
    ) {
      return [];
    }

    return array_filter($this->manifest[$chunk]['css'], fn($path) => is_string($path));
  }

  /**
   * Returns assets paths of given chunk.
   */
  public function getAssets(string $chunk): array {
    return $this->getChunkPropertyPaths('assets', $chunk);
  }

  /**
   * Checks if chunk exists in the manifest.
   */
  private function chunkExists(string $chunk): bool {
    return isset($this->manifest[$chunk]);
  }

  /**
   * Returns resolved paths of given chunk's property.
   */
  private function getChunkPropertyPaths(string $property, string $chunk): array {
    if (
      !$this->chunkExists($chunk)
      || !isset($this->manifest[$chunk][$property])
      || !is_array($this->manifest[$chunk][$property])
    ) {
      return [];
    }

    return array_filter(array_map(
      fn($import) => $this->getChunk($import),
      $this->manifest[$chunk][$property],
    ), fn($path) => is_string($path));
  }

}
