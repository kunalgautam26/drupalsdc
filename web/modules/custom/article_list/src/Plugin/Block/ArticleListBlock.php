<?php

namespace Drupal\article_list\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: "article_list_block",
  admin_label: new TranslatableMarkup("Recent Articles List"),
  category: new TranslatableMarkup("Custom")
)]
class ArticleListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  public function build() {
    return [
      '#lazy_builder' => [
        static::class . '::lazyBuild',
        [],
      ],
      '#create_placeholder' => TRUE,
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  public static function lazyBuild() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 5)
      ->accessCheck(TRUE);

    $nids = $query->execute();

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
      '#title' => t('Latest Articles'),
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

}
