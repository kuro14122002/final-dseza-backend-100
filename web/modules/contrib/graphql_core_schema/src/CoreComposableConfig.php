<?php

namespace Drupal\graphql_core_schema;

use Drupal\Core\Render\Element\Checkboxes;

/**
 * Wrapper for the graphql_core_schema configuration.
 */
class CoreComposableConfig {

  /**
   * Constructor.
   *
   * @param string[] $enabledEntityTypes
   *   Array of enabled entity types.
   * @param string[][] $enabledFields
   *   Array of enabled fields.
   * @param string[][] $enabledEntityFields
   *   Enabled fields for the Entity interface.
   * @param bool $valueFields
   *   Whether to generate value fields.
   */
  public function __construct(
    protected array $enabledEntityTypes,
    protected array $enabledFields,
    protected array $enabledEntityFields,
    protected bool $valueFields
  ) {
  }

  /**
   * Create the config object from the configuration array.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return static
   */
  public static function fromConfiguration(array $configuration): static {
    return new self(
      Checkboxes::getCheckedCheckboxes($configuration['enabled_entity_types'] ?? []),
      $configuration['fields'] ?? [],
      Checkboxes::getCheckedCheckboxes($configuration['entity_base_fields']['fields'] ?? []),
      !empty($configuration['generate_value_fields'])
    );
  }

  /**
   * Get enabled Entity interface fields.
   *
   * @return string[]
   *   The fields that are enabled.
   */
  public function getEnabledEntityFields(): array {
    return array_merge($this->enabledEntityFields, ['id']);
  }

  /**
   * Check if the given entity type is enabled.
   *
   * @param string $entityTypeId
   *   The ID of the entity type.
   *
   * @return bool
   *   TRUE if the entity type is enabled.
   */
  public function isEntityTypeEnabled(string $entityTypeId): bool {
    return in_array($entityTypeId, $this->enabledEntityTypes);
  }

  /**
   * Whether to generate value fields.
   *
   * @return bool
   *   TRUE if value fields should be generated.
   */
  public function shouldGeneratedValueFields(): bool {
    return $this->valueFields;
  }

  /**
   * Get the enabled entity types.
   *
   * @return string[]
   *   The enabled entity types.
   */
  public function getEnabledEntityTypes(): array {
    return $this->enabledEntityTypes;
  }

  /**
   * Check if the given field is enabled.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $fieldName
   *   The field name.
   *
   * @return bool
   *   TRUE if the field is enabled for this entity type.
   */
  public function fieldIsEnabled(string $entityTypeId, string $fieldName) {
    if (empty($this->enabledFields[$entityTypeId])) {
      return FALSE;
    }
    return in_array($fieldName, $this->enabledFields[$entityTypeId]);
  }

}
