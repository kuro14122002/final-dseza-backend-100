<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * @SchemaExtension(
 *   id = "dseza_menu_extension",
 *   name = "DSEZA Menu extension",
 *   description = "Adds menu related fields to the GraphQL API.",
 *   schema = "graphql_compose"
 * )
 * 
 * DISABLED: This extension has been disabled to avoid conflicts with GraphQL Compose.
 * GraphQL Compose already provides comprehensive Menu support with langcode field.
 * Use GraphQL Compose's built-in Menu functionality instead with queries like:
 * menuByName(name: MAIN) { ... }
 */
class MenuSchemaExtension extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    // Disabled to avoid conflicts with GraphQL Compose MenuItem type
    // All menu functionality is now handled by GraphQL Compose
    return;
    
    // Previous implementation commented out:
    // $builder = new ResolverBuilder();
    // $this->addMenuFields($registry, $builder);
  }

  /**
   * Add menu fields to the schema.
   * 
   * @deprecated This method is no longer used.
   */
  protected function addMenuFields(ResolverRegistryInterface $registry, ResolverBuilder $builder): void {
    // GraphQL Compose already provides langcode field for Menu entities
    // No custom resolver needed
  }

} 