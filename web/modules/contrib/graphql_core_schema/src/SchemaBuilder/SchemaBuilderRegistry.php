<?php

namespace Drupal\graphql_core_schema\SchemaBuilder;

/**
 * The registry for the graphql_core_schema schema.
 */
class SchemaBuilderRegistry {

  /**
   * The generated schema.
   *
   * @var \Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderType[]
   */
  protected array $types = [];

  /**
   * Array of types that have been or will be generated.
   *
   * @var string[]
   */
  protected array $generatedTypeNames = [];

  /**
   * Check if a type with the name is generated or is being generated.
   *
   * @param string $name
   *   The name of the type.
   *
   * @return bool
   *   The type exists or will exist.
   */
  public function typeWillExist(string $name): bool {
    return !empty($this->types[$name]) || in_array($name, $this->generatedTypeNames);
  }

  /**
   * Add a type.
   *
   * @param SchemaBuilderType $type
   *   The type.
   *
   * @return static
   */
  public function addType(SchemaBuilderType $type): static {
    $this->types[$type->getName()] = $type;
    return $this;
  }

  /**
   * Get a type.
   *
   * @param string $name
   *   The name of the type.
   *
   * @return SchemaBuilderType|null
   *   The type if it exists.
   */
  public function getType(string $name): SchemaBuilderType|null {
    return $this->types[$name] ?? NULL;
  }

  /**
   * Get types.
   *
   * @return SchemaBuilderType[]
   *   The types.
   */
  public function getTypes(): array {
    return $this->types;
  }

  /**
   * Create an interface.
   *
   * @param string $name
   *   The name of the interface.
   * @param string $description
   *   The description.
   * @param SchemaBuilderField[] $fields
   *   An array of fields.
   * @param string[] $interfaces
   *   Additional interfaces the interface implements.
   *
   * @return SchemaBuilderInterfaceType
   *   The interface.
   */
  public function createOrExtendInterface(string $name, string $description, array $fields, array $interfaces): SchemaBuilderInterfaceType {
    $type = $this->types[$name] ?? new SchemaBuilderInterfaceType($name);

    if (empty($type->getDescription())) {
      $type->description($description);
    }

    foreach ($fields as $field) {
      $type->addField($field);
    }

    foreach ($interfaces as $interface) {
      $type->addInterface($interface);
    }

    $this->types[$name] = $type;
    $this->generatedTypeNames[] = $name;
    return $type;
  }

  /**
   * Create a type.
   *
   * @param string $name
   *   The name of the interface.
   *
   * @return SchemaBuilderObjectType
   *   The type.
   */
  public function createType(string $name): SchemaBuilderObjectType {
    if (!empty($this->types[$name])) {
      return $this->types[$name];
    }
    $type = new SchemaBuilderObjectType($name);

    $this->types[$name] = $type;
    $this->generatedTypeNames[] = $name;
    return $type;
  }

  /**
   * Adds the given type to the list of types that will be generated.
   *
   * @param string $name
   *   The name of the type.
   *
   * @return static
   *   The type.
   */
  public function addGeneratedTypeName(string $name): static {
    $this->generatedTypeNames[] = $name;
    return $this;
  }

}
