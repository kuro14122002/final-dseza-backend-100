<?php

namespace Drupal\graphql_core_schema\SchemaBuilder;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;

/**
 * Class for a field on a type/interface or an argument.
 */
class SchemaBuilderField {

  /**
   * The type.
   */
  public string $type = '';

  /**
   * The description.
   */
  public string $description = '';

  /**
   * The Drupal machine name if the field is an entity / data type field.
   */
  public string|null $machineName = NULL;

  /**
   * If the field is a value field.
   */
  public bool $valueField = FALSE;

  /**
   * If the type is a non nullable list.
   */
  public array $typeModifiers = [];

  /**
   * The arguments.
   *
   * @var SchemaBuilderField[]
   */
  public array $arguments = [];

  /**
   * Construct a new schema builder field.
   *
   * @param string $name
   *   The name.
   */
  public function __construct(
    protected string $name,
  ) {
  }

  /**
   * Create a SchemaBuilderField from a FieldDefinitionNode.
   *
   * @param FieldDefinitionNode|InputValueDefinitionNode $node
   *   The node.
   *
   * @return static
   *   The SchemaBuilderField.
   */
  public static function createFromNode(FieldDefinitionNode|InputValueDefinitionNode $node): static {
    $field = new static($node->name->value);

    $description = $node->description?->value;
    if ($description) {
      $field->description($description);
    }

    $typeNode = $node->type;

    while ($typeNode) {
      if ($typeNode instanceof NamedTypeNode) {
        $field->type($typeNode->name->value);
        $typeNode = NULL;
      }
      elseif ($typeNode instanceof ListTypeNode) {
        $field->list();
        $typeNode = $typeNode->type;
      }
      elseif ($typeNode instanceof NonNullTypeNode) {
        $field->nonNullable();
        $typeNode = $typeNode->type;
      }
    }

    if (!empty($node->arguments)) {
      /** @var \GraphQL\Language\AST\InputValueDefinitionNode $argument */
      foreach ($node->arguments as $argument) {
        $field->argument(self::createFromNode($argument));
      }
    }

    return $field;
  }

  /**
   * Set the machine name.
   *
   * @param string $name
   *   The name.
   *
   * @return static
   */
  public function machineName(string $name): static {
    $this->machineName = $name;
    return $this;
  }

  /**
   * Set the entity field name.
   *
   * @param string $type
   *   The type.
   *
   * @return static
   */
  public function type(string $type): static {
    $this->type = $type;
    return $this;
  }

  /**
   * Set value field.
   *
   * @return static
   */
  public function valueField(): static {
    $this->valueField = TRUE;
    return $this;
  }

  /**
   * Set array.
   *
   * @return static
   */
  public function list(): static {
    $this->typeModifiers[] = 'list';
    return $this;
  }

  /**
   * Set non-nullable.
   *
   * @return static
   */
  public function nonNullable(): static {
    $this->typeModifiers[] = 'non-null';
    return $this;
  }

  /**
   * Set description.
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
   * Add an argument.
   *
   * @param SchemaBuilderField $argument
   *   The argument.
   *
   * @return static
   */
  public function argument(SchemaBuilderField $argument): static {
    $this->arguments[] = $argument;
    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get machine name.
   *
   * @return string|null
   *   The machine name.
   */
  public function getMachineName(): string|null {
    return $this->machineName;
  }

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    $description = $this->description;
    if ($this->machineName) {
      $description = "{field: $this->machineName} " . $description;
    }
    if ($this->valueField) {
      $description = "{value} " . $description;
    }
    return $description;
  }

}
