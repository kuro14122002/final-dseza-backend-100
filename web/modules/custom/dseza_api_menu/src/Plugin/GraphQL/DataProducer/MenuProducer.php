<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\DataProducer;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DataProducer(
 *   id = "dseza_menu",
 *   name = @Translation("Menu"),
 *   description = @Translation("Loads a menu by name."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Menu items")
 *   ),
 *   consumes = {
 *     "menu_name" = @ContextDefinition("string",
 *       label = @Translation("Menu name")
 *     )
 *   }
 * )
 */
class MenuProducer extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_link_tree) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree')
    );
  }

  /**
   * Resolver.
   *
   * @param string $menu_name
   *   The menu name.
   *
   * @return mixed
   *   The menu items.
   */
  public function resolve($menu_name) {
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(0); // Load all levels
    $parameters->onlyEnabledLinks();

    $tree = $this->menuLinkTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    return $this->buildMenuItemsArray($tree);
  }

  /**
   * Build menu items array recursively.
   *
   * @param array $tree
   *   The menu tree.
   *
   * @return array
   *   The menu items array.
   */
  private function buildMenuItemsArray(array $tree) {
    $items = [];
    foreach ($tree as $element) {
      $link = $element->link;
      $items[] = $link;
    }
    return $items;
  }

} 