<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Calls the given method on the object.
 *
 * @DataProducer(
 *   id = "call_method",
 *   name = @Translation("Call method"),
 *   description = @Translation("Calls the method on the object."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Result")
 *   ),
 *   consumes = {
 *     "object" = @ContextDefinition("any",
 *       label = @Translation("Object")
 *     ),
 *     "method" = @ContextDefinition("string",
 *       label = @Translation("Method")
 *     ),
 *   }
 * )
 */
class CallMethod extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param object $object
   * @param string $method
   *
   * @return mixed
   *   The return value of the method.
   */
  public function resolve(object $object, string $method) {
    if (method_exists($object, $method)) {
      return $object->$method();
    }
  }

}
