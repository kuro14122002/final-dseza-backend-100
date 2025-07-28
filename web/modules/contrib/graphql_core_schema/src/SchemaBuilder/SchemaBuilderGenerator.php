<?php

namespace Drupal\graphql_core_schema\SchemaBuilder;

use Drupal\graphql_core_schema\CoreComposableConfig;
use Drupal\graphql_core_schema\Form\CoreComposableSchemaFormHelper;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\SchemaPrinter;

/**
 * The schema builder generator.
 *
 * This class is used to build the final base GraphQL schema given the
 * generated schema builder types and existing base definition types.
 */
class SchemaBuilderGenerator {

  /**
   * Array of generated GraphQL type definitions.
   *
   * @var Type[]
   */
  protected array $graphqlTypes = [];

  /**
   * Add a GraphQL type.
   *
   * @param Type $type
   *   The type to add.
   *
   * @return static
   */
  public function addType(Type $type): static {
    $this->graphqlTypes[$type->name] = $type;
    return $this;
  }

  /**
   * Get the generated schema.
   *
   * @param SchemaBuilderRegistry $registry
   *   The registry of core schema types.
   * @param CoreComposableConfig $config
   *   The core composable configuration.
   * @param \GraphQL\Language\AST\DefinitionNode[] $baseDefinitions
   *   Array of parsed base definition types.
   *
   * @return string
   *   The generated schema.
   */
  public function getGeneratedSchema(SchemaBuilderRegistry $registry, CoreComposableConfig $config, array $baseDefinitions): string {
    // First generate already existing types. These include types and
    // interfaces defined by graphql_core_schema (such as Entity,
    // FieldItemList, etc.) and those defined by schema extensions in their
    // base.graphqls files.
    // AST nodes that are neither an object nor an interface type.
    $otherAstNodes = [];
    foreach ($baseDefinitions as $node) {
      $name = $node->name->value;
      $existing = $registry->getType($name);

      if ($node instanceof ObjectTypeDefinitionNode) {
        $type = SchemaBuilderObjectType::createFromNode($node);
        if ($existing) {
          $existing->merge($type);
        }
        else {
          $registry->addType($type);
        }
      }
      elseif ($node instanceof InterfaceTypeDefinitionNode) {
        $type = SchemaBuilderInterfaceType::createFromNode($node);

        if ($type->getName() === 'Entity') {
          $enabled_field = $config->getEnabledEntityFields();
          $white_listed = CoreComposableSchemaFormHelper::BASE_ENTITY_FIELD_DEFINITIONS;
          $fields = $type->getFields();
          $result = array_flip(array_diff(array_keys($fields), array_keys($white_listed)));

          // Do not remove custom fields that might have been added by an extension.
          // This is for example important for the field "reverseReference".
          $custom_fields = array_intersect_key($fields, $result);
          if (!empty($custom_fields)) {
            $custom_field_keys = array_keys($custom_fields);
            $enabled_field = array_merge($enabled_field, $custom_field_keys);
          }

          // Merge
          $type->keepFields($enabled_field);
        }

        if ($existing) {
          $existing->merge($type);
        }
        else {
          $registry->addType($type);
        }
      }
      else {
        // Anything not a type or interface, like enums.
        $otherAstNodes[] = $node;
      }
    }

    // Add interface fields to all types.
    foreach ($registry->getTypes() as $type) {
      foreach ($type->interfaces as $interfaceName) {
        $interface = $registry->getType($interfaceName);
        if ($interface) {
          $interfaceFields = $interface->getFields();
          foreach ($interfaceFields as $interfaceField) {
            // This interface allows us to override the type of the translation/
            // translations fields with the type of the implementing type,
            // because the translation of an entity is always going to be the
            // same entity.
            if ($interfaceName === 'EntityTranslatable') {
              $clone = clone $interfaceField;
              $clone->type = $type->getName();
              $type->addField($clone);
            }
            else {
              $type->addField($interfaceField);
            }
          }
        }
      }
    }

    // Now generate all the types.
    foreach ($registry->getTypes() as $type) {
      $this->generateGraphqlType($registry, $type);
    }

    // Generate the final schema to string.
    return implode("\n\n", array_merge(
      array_map([SchemaPrinter::class, 'printType'], $this->graphqlTypes),
      array_map([Printer::class, 'doPrint'], $otherAstNodes),
    ));
  }

  /**
   * Get fields for a type.
   *
   * @param SchemaBuilderField $field
   *   The field.
   *
   * @return array
   *   The field definition.
   */
  private function buildField(SchemaBuilderField $field): array {
    $type = $this->generateFieldType($field->type);
    foreach (array_reverse($field->typeModifiers) as $modifier) {
      if ($modifier === 'list') {
        $type = Type::listOf($type);
      }
      elseif ($modifier === 'non-null') {
        $type = Type::nonNull($type);
      }
    }

    $fieldConfig = [
      'description' => $field->getDescription(),
      'args' => [],
      'type' => $type,
    ];

    foreach ($field->arguments as $argument) {
      $fieldConfig['args'][$argument->getName()] = $this->buildField($argument);
    }

    return $fieldConfig;
  }

  /**
   * Generate the GraphQL type for a schema builder type.
   *
   * @param SchemaBuilderRegistry $registry
   *   The schema builder registry.
   * @param SchemaBuilderType $type
   *   The schema builder type.
   *
   * @return ObjectType|InterfaceType
   *   The generated type.
   */
  private function generateGraphqlType(SchemaBuilderRegistry $registry, SchemaBuilderType $type) {
    if (!empty($this->graphqlTypes[$type->getName()])) {
      return $this->graphqlTypes[$type->getName()];
    }

    $config = [
      'name' => $type->getName(),
      'description' => $type->getDescription(),
      'fields' => [],
      'interfaces' => [],
    ];

    foreach ($type->getFields() as $name => $field) {
      $config['fields'][$name] = $this->buildField($field);
    }

    foreach ($type->interfaces as $interfaceName) {
      $interface = $registry->getType($interfaceName);
      if ($interface) {
        $config['interfaces'][] = $this->generateGraphqlType($registry, $interface);
      }
    }

    $graphqlType = $type instanceof SchemaBuilderObjectType ? new ObjectType($config) : new InterfaceType($config);
    $this->graphqlTypes[$type->getName()] = $graphqlType;
    return $graphqlType;
  }

  /**
   * Build the type definition.
   *
   * @param string $typeName
   *   The name of the type.
   *
   * @return Type
   *   The type.
   */
  private function generateFieldType(string $typeName): Type {
    return match($typeName) {
      'String' => Type::string(),
      'Int' => Type::int(),
      'Float' => Type::float(),
      'Boolean' => Type::boolean(),
      'ID' => Type::id(),
      // Because we generate each type individually and convert it to a
      // string, we can just return a new type here - it won't be generated
      // twice. It also doesn't matter if it's an interface. It ends up being
      // generated as a string.
      default => new ObjectType(['name' => $typeName]),
    };
  }

  /**
   * Get all generated type names.
   *
   * @return string[]
   *   The generated type names.
   */
  public function getGeneratedTypeNames(): array {
    return array_keys($this->graphqlTypes);
  }

}
