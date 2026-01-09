<?php

namespace Drupal\vite\Twig;

use Drupal\vite\Vite;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides a Twig extension for Vite.
 */
class TwigExtension extends AbstractExtension {

  public function __construct(protected Vite $vite) {
  }

  /**
   * Returns a list of available Twig functions.
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('vite_get_chunk_path', [$this->vite, 'getChunk']),
    ];
  }

  /**
   * Returns the extension name.
   */
  public function getName(): string {
    return 'vite.twig_extension';
  }

}
