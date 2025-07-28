<?php

namespace Drupal\graphql_core_schema\EventSubscriber;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\EventSubscriber\CurrentLanguageResetTrait;
use Drupal\graphql\SubRequestResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the callback for GraphQL subrequests.
 *
 * This subscriber replaces the subscriber provided by the graphql module.
 */
class CoreSchemaSubrequestSubscriber implements EventSubscriberInterface {

  use CurrentLanguageResetTrait;

  /**
   * Constructs a CoreSchemaSubrequestSubscriber object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   *   The translator.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\language\LanguageNegotiatorInterface $languageNegotiator
   *   The language negotiator.
   */
  public function __construct(
    protected $languageManager,
    protected $translator,
    protected $currentUser,
    protected RendererInterface $renderer,
    protected $languageNegotiator = NULL,
  ) {
  }

  /**
   * Handle kernel response events.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel event object.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$request->attributes->has('_graphql_subrequest')) {
      return;
    }

    $callback = $request->attributes->get('_graphql_subrequest');

    $context = new RenderContext();
    $result = $this->renderer->executeInRenderContext($context, function () use ($callback) {
      return $callback();
    });

    $response = new SubRequestResponse($result);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    $event->setResponse($response);

    $this->resetLanguageContext();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onKernelResponse',
    ];
  }

}
