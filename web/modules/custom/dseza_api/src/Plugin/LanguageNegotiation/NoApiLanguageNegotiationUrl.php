<?php

namespace Drupal\dseza_api\Plugin\LanguageNegotiation;

use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Overrides URL language negotiation to exclude API paths from prefixes.
 */
class NoApiLanguageNegotiationUrl extends LanguageNegotiationUrl {

  /**
   * Determines if a path is an API path that should not be language-prefixed.
   */
  protected function isApiPath(string $path): bool {
    $normalized = '/' . ltrim($path, '/');
    return (bool) preg_match('#^/(api(?:/|$)|jsonapi(?:/|$)|graphql(?:/|$))#i', $normalized);
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    if ($request && $this->isApiPath($request->getPathInfo())) {
      // Do not negotiate language based on URL for API paths.
      return NULL;
    }
    return parent::getLangcode($request);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // Do not add language prefix or domain for API paths.
    if ($this->isApiPath($path)) {
      return $path;
    }
    return parent::processOutbound($path, $options, $request, $bubbleable_metadata);
  }
}


