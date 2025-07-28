<?php

namespace Drupal\dseza_api\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to set language context for GraphQL endpoints.
 */
class LanguageDetectionSubscriber implements EventSubscriberInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new LanguageDetectionSubscriber.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 100];
    return $events;
  }

  /**
   * Sets language context based on GraphQL endpoint URL.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Check if this is a language-specific GraphQL endpoint
    if (preg_match('#^/(en|vi)/graphql/#', $path, $matches)) {
      $langcode = $matches[1];
      
      // Set the language context
      $language = $this->languageManager->getLanguage($langcode);
      if ($language) {
        $this->languageManager->setConfigOverrideLanguage($language);
        
        // Store language in request attributes for GraphQL context
        $request->attributes->set('_graphql_language', $langcode);
      }
    }
  }

} 