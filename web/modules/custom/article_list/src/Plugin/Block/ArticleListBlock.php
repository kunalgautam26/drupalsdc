<?php

namespace Drupal\article_list\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: "article_list_block",
  admin_label: new TranslatableMarkup("Recent Articles List"),
  category: new TranslatableMarkup("Custom")
)]
class ArticleListBlock extends BlockBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  public function build() {
    // Get current language code
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    
    return [
      '#lazy_builder' => [
        static::class . '::lazyBuild',
        [$current_language], // Pass language as parameter
      ],
      '#create_placeholder' => TRUE,
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['user.permissions', 'languages:language_interface'],
      ],
    ];
  }

  /**
   * 
   */
  public static function lazyBuild($language_code = NULL) {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    
    // Get current language if not passed
    if ($language_code === NULL) {
      $language_code = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $query = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('langcode', $language_code) // Filter by language
      ->sort('created', 'DESC')
      ->range(0, 5)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [
        '#markup' => '<p>' . t('No articles found in the current language.') . '</p>',
        '#cache' => [
          'tags' => ['node_list:article'],
          'contexts' => ['languages:language_interface'],
        ],
      ];
    }

    $nodes = $storage->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      // Check if node has translation in current language
      if ($node->hasTranslation($language_code)) {
        $translated_node = $node->getTranslation($language_code);
        $items[] = [
          '#type' => 'link',
          '#title' => $translated_node->getTitle(),
          '#url' => $translated_node->toUrl(),
        ];
      }
      else {
        // Fallback to original if translation doesn't exist
        $items[] = [
          '#type' => 'link',
          '#title' => $node->getTitle(),
          '#url' => $node->toUrl(),
        ];
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Latest Articles'),
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['languages:language_interface', 'user.permissions'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['lazyBuild'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['node_list:article'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['languages:language_interface', 'user.permissions'];
  }

}