<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The data producer for language switcher links.
 *
 * @DataProducer(
 *   id = "language_switch_links",
 *   name = @Translation("Language Switch Links"),
 *   description = @Translation("Return the language switch links for an URL."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Language Switch Links")
 *   ),
 *   consumes = {
 *     "url" = @ContextDefinition("any",
 *       label = @Translation("Route URL"),
 *     )
 *   }
 * )
 */
class LanguageSwitchLinks extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('router.no_access_checks')
    );
  }

  /**
   * LanguageSwitchLinks constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\Routing\RouterInterface $routing
   *   The routing service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
    protected RendererInterface $renderer,
    protected RouterInterface $routing
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * The resolver.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $fieldContext
   *   The field context.
   *
   * @return array
   *   The language switch links.
   */
  public function resolve(Url $url, FieldContext $fieldContext) {
    $result = $this->languageManager->getLanguageSwitchLinks(LanguageInterface::TYPE_URL, $url);
    $links = ($result ? $result->links : []) ?? [];
    $currentLangcode = $fieldContext->getContextValue(name: 'language') ?? $this->languageManager->getCurrentLanguage()->getId();
    $context = new RenderContext();

    foreach ($links as $langcode => $link) {
      /** @var \Drupal\Core\Url $url */
      $url = $link['url'];
      $entities = [];
      $accessible_translations = [];
      if ($url->isRouted()) {
        [$entities, $accessible_translations] = $this->renderer->executeInRenderContext($context, function () use ($url) {
          return $this->getEntitiesAndTranslations($url);
        });
      }

      /** @var \Drupal\language\Entity\ConfigurableLanguage $linkLanguage */
      $linkLanguage = $link['language'];
      $links[$langcode]['active'] = $linkLanguage->getId() === $currentLangcode;

      // Language dependant url.
      $url->setOptions(
        [
          'query' => $link['query'],
          'language' => $link['language'],
          'attributes' => $link['attibutes'] ?? [],
        ]
      );

      if ($entities && !in_array($langcode, $accessible_translations)) {
        unset($links[$langcode]);
      }
    }

    if (!$context->isEmpty()) {
      $fieldContext->addCacheableDependency($context->pop());
    }

    return $links;
  }

  /**
   * Get current route's translatable entities and accessible translations.
   *
   * @param Url $url
   *   The URL to get the entities from.
   */
  private function getEntitiesAndTranslations(Url $url) {
    $entities = [];
    $accessible_translations = [];
    $router_match = $this->routing->match($url->toString());

    // Find upcasted route entity.
    foreach (array_keys($url->getRouteParameters()) as $key) {
      if (!empty($router_match[$key])) {
        $entity = $router_match[$key];
        if ($entity instanceof TranslatableInterface) {
          $entities[] = $entity;
          $accessible_translations = array_merge(
            $accessible_translations,
            array_filter(array_keys($entity->getTranslationLanguages()), function ($langcode) use ($entity) {
              $translation = method_exists($entity, 'getTranslation') ? $entity->getTranslation($langcode) : FALSE;
              return $translation && method_exists($translation, 'access') && $translation->access('view');
            })
          );
        }
      }
    }
    return [$entities, $accessible_translations];
  }

}
