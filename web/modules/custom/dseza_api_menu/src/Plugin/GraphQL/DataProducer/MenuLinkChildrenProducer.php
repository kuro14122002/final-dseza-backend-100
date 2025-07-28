<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\DataProducer;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DataProducer(
 *   id = "menu_link_children",
 *   name = @Translation("Menu link children"),
 *   description = @Translation("Returns the menu link children."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Menu link children")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link")
 *     ),
 *     "menu_name" = @ContextDefinition("string",
 *       label = @Translation("Menu name")
 *     )
 *   }
 * )
 */
class MenuLinkChildrenProducer extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
   * @param mixed $link
   *   The menu link.
   * @param string $menu_name
   *   The menu name.
   *
   * @return array
   *   The menu link children.
   */
  public function resolve($link, $menu_name) {
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link->getPluginId());
    $parameters->excludeRoot();
    $parameters->setMaxDepth(1);
    $parameters->onlyEnabledLinks();

    $tree = $this->menuLinkTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    $children = [];
    foreach ($tree as $element) {
      $children[] = $element->link;
    }

    return $children;
  }

} 