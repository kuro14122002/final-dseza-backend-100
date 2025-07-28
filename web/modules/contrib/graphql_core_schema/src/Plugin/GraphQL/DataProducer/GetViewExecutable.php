<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\views\ViewEntityInterface;

/**
 * The data producer to return the view executable.
 *
 * @DataProducer(
 *   id = "view_executable",
 *   name = @Translation("View Executable"),
 *   description = @Translation("Return the view executable."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Executable")
 *   ),
 *   consumes = {
 *     "view" = @ContextDefinition("any",
 *       label = @Translation("View"),
 *     ),
 *     "displayId" = @ContextDefinition("string",
 *       label = @Translation("Display ID"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class GetViewExecutable extends DataProducerPluginBase {

  /**
   * The resolver.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view.
   * @param string $displayId
   *   The display ID.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $fieldContext
   *   The field context.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view executable.
   */
  public function resolve(ViewEntityInterface $view, $displayId, FieldContext $fieldContext) {
    $executable = $view->getExecutable();
    $executable->initDisplay();
    $executable->initHandlers();

    if ($displayId) {
      $executable->setDisplay($displayId);
    }
    $fieldContext->addCacheableDependency($executable);
    return $executable;
  }

}
