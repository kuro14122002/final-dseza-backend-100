<?php

namespace Drupal\graphql_core_schema\SchemaBuilder;

use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

/**
 * The base class for a type in the schema.
 */
class SchemaBuilderType {

  /**
   * The description.
   */
  protected string $description = '';

  /**
   * The fields.
   *
   * @var SchemaBuilderField[]
   */
  protected array $fields = [];

  /**
   * The interfaces implemented by this type.
   *
   * @var string[]
   */
  public array $interfaces = [];

  /**
   * Construct a new schema builder type.
   *
   * @param string $name
   *   The name.
   */
  public function __construct(
    protected string $name,
  ) {
  }

  /**
   * Create a SchemaBuilderType from a type definition node.
   *
   * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $node
   *   The type definition node.
   *
   * @return static
   *   The schema builder type.
   */
  public static function createFromNode(ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $node): static {
    $type = new static($node->name->value);
    $description = $node->description?->value;
    if ($description) {
      $type->description($description);
    }

    /** @var \GraphQL\Language\AST\NamedTypeNode $interface */
    foreach ($node->interfaces as $interface) {
      $type->addInterface($interface->name->value);
    }

    /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldNode */
    foreach ($node->fields as $fieldNode) {
      $field = SchemaBuilderField::createFromNode($fieldNode);
      $type->addField($field);
    }
    return $type;
  }

  /**
   * Get the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Add a field.
   *
   * @param string $name
   *   The name.
   * @param string $type
   *   The type.
   *
   * @return SchemaBuilderField
   *   The field.
   */
  public function createField(
    string $name,
    string $type,
  ): SchemaBuilderField {
    $field = new SchemaBuilderField($name, $type);
    $this->fields[] = $field;
    return $field;
  }

  /**
   * Set the description.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   */
  public function description(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Add a field.
   *
   * @param SchemaBuilderField $field
   *   The field.
   *
   * @return static
   */
  public function addField(SchemaBuilderField $field): static {
    $this->fields[] = $field;
    return $this;
  }

  /**
   * Add an interface.
   *
   * @param string $name
   *   The name of the interface.
   */
  public function addInterface(string $name): static {
    $this->interfaces[] = $name;
    return $this;
  }

  /**
   * Get the fields.
   *
   * @param bool $uniqueMachineNames
   *   Remove duplicate fields based on their machine name.
   *
   * @return SchemaBuilderField[]
   *   The fields.
   */
  public function getFields(bool $uniqueMachineNames = TRUE): array {
    if (!$uniqueMachineNames) {
      return $this->fields;
    }

    $unique = [];
    $seen = [];

    foreach ($this->fields as $field) {
      $machineName = $field->getMachineName();
      if ($machineName) {
        // The key is the machine name + an indicator if its a value field.
        // This is needed because value fields and raw fields have the same
        // machine name.
        $key = $machineName . 'value:' . (int) $field->valueField;
        if (in_array($key, $seen)) {
          continue;
        }
        $seen[] = $key;
      }

      $fieldName = $field->getName();

      // Field with this name has already been generated. Use machine name
      // instead.
      if (!empty($unique[$fieldName])) {
        $fieldName = $machineName;
      }

      if ($fieldName) {
        $unique[$fieldName] = $field;
      }
    }

    return $unique;
  }

  /**
   * Merge the fields and descriptions from the given type.
   *
   * @param static $type
   *   The other type.
   *
   * @return SchemaBuilderField[]
   *   The fields.
   */
  public function merge(self $type): static {
    $this->fields = array_merge($this->fields, $type->fields);
    $this->interfaces = array_merge($this->interfaces, $type->interfaces);
    return $this;
  }

  /**
   * Remove fields that are not provided in the array.
   *
   * @param string[] $fieldNames
   *   The name of the fields to keep.
   *
   * @return static
   */
  public function keepFields(array $fieldNames): static {
    $this->fields = array_filter($this->fields, static function (SchemaBuilderField $field) use ($fieldNames) {
      return in_array($field->getName(), $fieldNames);
    });
    return $this;
  }

}
