<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeEntityType(
 *   id = "menu",
 *   base_fields = {
 *     "name" = {
 *       "field_type" = "entity_label",
 *       "required" = TRUE,
 *       "description" = @Translation("The menu name."),
 *     },
 *     "langcode" = {
 *       "field_type" = "language",
 *       "required" = FALSE,
 *       "description" = @Translation("The language of the menu."),
 *     },
 *     "items" = {
 *       "type_sdl" = "MenuItem",
 *       "multiple" = TRUE,
 *       "required" = TRUE,
 *       "description" = @Translation("The menu items."),
 *     },
 *   },
 * )
 */
class Menu extends GraphQLComposeEntityTypeBase {

  /**
   * {@inheritdoc}
   *
   * Force add a query for menus to the base Query.
   */
  public function registerTypes(): void {
    parent::registerTypes();

    $extension = new ObjectType([
      'name' => 'Query',
      'fields' => fn() => [
        'menu' => [
          'type' => $this->gqlSchemaTypeManager->get('Menu'),
          'description' => (string) $this->t('Load a Menu by name.'),
          'args' => array_filter([
            'name' => [
              'type' => Type::nonNull($this->gqlSchemaTypeManager->get('MenuAvailable')),
              'description' => (string) $this->t('Internal menu name. Eg MAIN'),
            ],
            'langcode' => $this->languageManager->isMultilingual() ? [
              'type' => Type::string(),
              'description' => (string) $this->t('Optionally set the response language. Eg en, ja, fr. Setting this langcode will change the current language of the entire response.'),
            ] : [],
          ]),
        ],
      ],
    ]);

    $this->gqlSchemaTypeManager->extend($extension);
  }

}
