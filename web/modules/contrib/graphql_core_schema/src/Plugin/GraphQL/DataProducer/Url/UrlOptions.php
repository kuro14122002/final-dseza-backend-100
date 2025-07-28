<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer\Url;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Produces the URL options.
 *
 * @DataProducer(
 *   id = "url_options",
 *   name = @Translation("URL Options"),
 *   description = @Translation("The URL options."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("URL Options")
 *   ),
 *   consumes = {
 *     "options" = @ContextDefinition("any",
 *       label = @Translation("The raw URL options."),
 *     )
 *   }
 * )
 */
class UrlOptions extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
    );
  }

  /**
   * The constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * The resolver.
   *
   * @param array $optionsInput
   *   The input options.
   *
   * @return array
   *   The URL options.
   */
  public function resolve(array $optionsInput) {
    $options = $optionsInput;

    if (!empty($options['language']) && is_string($options['language'])) {
      $language = $this->languageManager->getLanguage(strtolower($options['language']));
      $options['language'] = $language;
    }

    return $options;
  }

}
