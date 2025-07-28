<?php

namespace Drupal\graphql_messenger;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * Wrapper for collecting messenger messages.
 */
class MessageWrapper implements \JsonSerializable {

  public function jsonSerialize(): mixed {
    $messenger = \Drupal::messenger();
    $messages = $messenger->deleteAll();
    $messagesParsed = [];

    foreach ($messages as $type => $typeMessages) {
      foreach ($typeMessages as $message) {
        $messagesParsed[] = [
          'type' => $type,
          'message' => $message,
          'escaped' => Html::escape($message),
          'safe' => Xss::filter($message),
        ];
      }
    }
    return $messagesParsed;
  }

}
