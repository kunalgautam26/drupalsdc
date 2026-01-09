<?php

namespace Drupal\article_list\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Recent Articles List' block.
 */
#[Block(
  id: "article_list_block",
  admin_label: new TranslatableMarkup("Recent Articles List"),
  category: new TranslatableMarkup("Custom")
)]
class ArticleListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new ArticleListBlock instance.
   *
   * @param array $configuration
   * A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   * The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   * The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $storage = $this->entityTypeManager->getStorage('node');

    // FIX: Use condition('status', 1) instead of sort('status', 1)
    $query = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1) // Only show published nodes
      ->sort('created', 'DESC')
      ->range(0, 5) // Good practice: Limit the list to 5 items
      ->accessCheck(TRUE); // Drupal 10 requires explicit access check

    $nids = $query->execute();

    // Return empty array if no results to hide block
    if (empty($nids)) {
      return [];
    }

    $nodes = $storage->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $items[] = [
        '#type' => 'link',
        '#title' => $node->getTitle(),
        '#url' => $node->toUrl(),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Latest Articles'),
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }
}