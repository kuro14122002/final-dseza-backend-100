<?php

namespace Drupal\auto_term_translate\Routing;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for entity translation routes.
 */
class AutoTermTranslateRouteSubscriber extends RouteSubscriberBase {

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * Constructs a AutoTermTranslateRouteSubscriber object.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   */
  public function __construct(ContentTranslationManagerInterface $content_translation_manager) {
    $this->contentTranslationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if ($entity_type_id == 'taxonomy_term') {
        $route = new Route(
          'taxonomy/{taxonomy_term}/auto-translate-form',
          [
            '_form' => 'Drupal\auto_term_translate\Form\TranslationForm',
            '_title' => 'Automatic Translation',
          ],
        );
        $route
          ->setRequirement('_access_auto_term_translation', 'taxonomy_term')
          ->setRequirement('taxonomy_term', '\d+')
          ->setOption('_admin_route', 'TRUE')
          ->setOption('parameters', ['taxonomy_term' => ['type' => 'entity:taxonomy_term']]);
        $collection->add("entity.$entity_type_id.auto_translation_add", $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Should run after AdminRouteSubscriber so the routes can inherit admin
    // status of the edit routes on entities. Therefore priority -210.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -210];
    return $events;
  }

}
