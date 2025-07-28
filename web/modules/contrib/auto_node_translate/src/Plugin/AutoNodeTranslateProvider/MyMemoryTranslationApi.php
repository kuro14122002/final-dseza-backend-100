<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate\Plugin\AutoNodeTranslateProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\auto_node_translate\AutoNodeTranslateProviderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Psr\Http\Client\ClientInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Plugin implementation of the auto_node_translate_provider.
 *
 * @AutoNodeTranslateProvider(
 *   id = "auto_node_translate_mymemory",
 *   label = @Translation("MyMemory"),
 *   description = @Translation("MyMemory translation provider for auto node translate.")
 * )
 */
final class MyMemoryTranslationApi extends AutoNodeTranslateProviderPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The http_client service.
   *
   * @var \Psr\Http\Client\ClientInterface
   */
  protected $httpClient;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Psr\Http\Client\ClientInterface $http_client
   *   The http_client service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    ConfigFactoryInterface $config,
    ClientInterface $http_client,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function translate($text, $languageFrom, $languageTo) : string {
    $from = explode('-', $languageFrom)[0];
    $to = explode('-', $languageTo)[0];
    $text = str_replace('&nbsp;', '%20', $text);
    $config = $this->config->get('auto_node_translate.my_memory_settings');
    $emailQuery = '';
    if (!empty($config->get('mm_email'))) {
      $emailQuery = '&de=' . $config->get('mm_email');
    }
    // Recursivity due to MyMemory limitation to 500 bytes length in query.
    if (strlen($text) > 400) {
      return $this->translate(substr($text, 0, 400), $languageFrom, $languageTo) . $this->translate(substr($text, 400), $languageFrom, $languageTo);
    }
    else {
      $url = 'https://api.mymemory.translated.net/get?q=' . $text . '&langpair=' . $from . '|' . $to . $emailQuery;
      try {
        $response = $this->httpClient->get($url);
      }
      catch (\Exception $e) {
        $this->messenger->addError($this->t('Error @code: @message', [
          '@code' => $e->getCode(),
          '@message' => $e->getMessage(),
        ]));
        return $text;
      }
      $data = (string) $response->getBody();
      $translation = Json::decode($data);
      if (!$translation['quotaFinished']) {
        $translatedText = html_entity_decode($translation['responseData']['translatedText']);
      }
      else {
        $translatedText = $text;
        $link = Url::fromRoute('auto_node_translate.settings', [], ['absolute' => TRUE])->toString();
        $this->messenger->addError($this->t('The translation cota has been exceeded for MyMemory try changing the default Api in <a href=@link>@link</a>'), ['@link' => $link]);
      }
      return $translatedText;
    }
  }

}
