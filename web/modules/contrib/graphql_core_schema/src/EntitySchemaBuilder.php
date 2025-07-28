<?php

namespace Drupal\graphql_core_schema;

use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\Field\Plugin\Field\FieldType\EmailItem;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItemBase;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderField;
use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderRegistry;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

/**
 * The EntitySchemaBuilder class.
 */
class EntitySchemaBuilder {

  /**
   * Types that should never be generated.
   *
   * @var string[]
   */
  const EXCLUDED_TYPES = [
    'password',
    '_core_config_info',
  ];

  /**
   * Fields that are not resolved by the default field resolver and exist on all entities.
   *
   * @var string[]
   */
  const EXCLUDED_ENTITY_FIELDS = [
    'id',
    'uuid',
    'label',
    'langcode',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManager|null $entityTypeManager = NULL;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface|null $entityFieldManager = NULL;

  /**
   * The entity type bundle info service.
   */
  protected EntityTypeBundleInfoInterface|null $entityTypeBundleInfo = NULL;

  /**
   * The type data manager.
   */
  protected TypedDataManagerInterface|null $typedDataManager = NULL;

  /**
   * The typed config manager.
   */
  protected TypedConfigManagerInterface|null $typedConfigManager = NULL;

  /**
   * The constructor.
   *
   * @param SchemaBuilderRegistry $registry
   *   The schema builder registry.
   * @param CoreComposableConfig $config
   *   The schema configuration.
   */
  public function __construct(
    protected SchemaBuilderRegistry $registry,
    protected CoreComposableConfig $config,
  ) {
  }

  /**
   * Get the entity type manager.
   *
   * @return EntityTypeManager
   *   The entity type manager.
   */
  private function getEntityTypeManager(): EntityTypeManager {
    if (empty($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::service('entity_type.manager');
    }

    return $this->entityTypeManager;
  }

  /**
   * Get the entity field manager.
   *
   * @return EntityFieldManagerInterface
   *   The entity field manager.
   */
  private function getEntityFieldManager(): EntityFieldManagerInterface {
    if (empty($this->entityFieldManager)) {
      $this->entityFieldManager = \Drupal::service('entity_field.manager');
    }

    return $this->entityFieldManager;
  }

  /**
   * Get the entity type bundle info service.
   *
   * @return EntityTypeBundleInfoInterface
   *   The entity type bundle info service.
   */
  private function getEntityTypeBundleInfo(): EntityTypeBundleInfoInterface {
    if (empty($this->entityTypeBundleInfo)) {
      $this->entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    }

    return $this->entityTypeBundleInfo;
  }

  /**
   * Get the typed data manager.
   *
   * @return \GraphQL\Type\Definition\TypedDataManagerInterface
   *   The typed data manager.
   */
  private function getTypedDataManager(): TypedDataManagerInterface {
    if (empty($this->typedDataManager)) {
      $this->typedDataManager = \Drupal::service('typed_data_manager');
    }

    return $this->typedDataManager;
  }

  /**
   * Get the typed config manager.
   *
   * @return \GraphQL\Type\Definition\TypedConfigManagerInterface
   *   The typed config manager.
   */
  private function getTypedConfigManager(): TypedConfigManagerInterface {
    if (empty($this->typedConfigManager)) {
      $this->typedConfigManager = \Drupal::service('config.typed');
    }

    return $this->typedConfigManager;
  }

  /**
   * Get the schema mapping for a config entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $type
   *   The config entity type.
   *
   * @return array
   *   The schema mapping.
   */
  private function getConfigEntityMapping(ConfigEntityTypeInterface $type): array {
    $configPrefix = $type->getConfigPrefix();
    $typedConfigDefinition = $this->getTypedConfigManager()->getDefinition($configPrefix . '.*');
    $mapping = $typedConfigDefinition['mapping'] ?? [];
    if (empty($mapping)) {
      $typedConfigDefinition = $this->getTypedConfigManager()->getDefinition($configPrefix . '.*.*');
      $mapping = $typedConfigDefinition['mapping'] ?? [];
    }
    if (empty($mapping)) {
      $typedConfigDefinition = $this->getTypedConfigManager()->getDefinition($configPrefix . '.*.*.*');
      $mapping = $typedConfigDefinition['mapping'] ?? [];
    }

    return $mapping;
  }

  /**
   * Create a field.
   */
  public function createField(string $name): SchemaBuilderField {
    return new SchemaBuilderField($name);
  }

  /**
   * Get the GraphQL field definition for an entity value field.
   *
   * This will return a type for value fields, where instead of the entire
   * field the direct field value is resolved. For example a simple text
   * field will directly resolve to a string scalar.
   *
   * If no appropriate scalar is found the type for the field item is returned.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return \GraphQL\Type\SchemaBuilderField|null
   *   The GraphQL type if found.
   */
  private function buildGraphqlValueField(FieldDefinitionInterface $fieldDefinition): SchemaBuilderField|null {
    $description = (string) $fieldDefinition->getDescription();
    $fieldName = $fieldDefinition->getName();

    if (!$description) {
      $description = (string) $fieldDefinition->getFieldStorageDefinition()->getDescription();
    }
    $storageDefinition = $fieldDefinition->getFieldStorageDefinition();
    $isMultiple = $storageDefinition->isMultiple();

    // Create a field item we can use to determine what scalar this should
    // resolve to.
    $itemDefinition = $fieldDefinition->getItemDefinition();
    $typedData = $this->getTypedDataManager()->create($itemDefinition);

    if ($fieldName === 'metatag' && $typedData instanceof MapItem) {
      return NULL;
    }
    $valueFieldName = EntitySchemaHelper::toCamelCase($fieldName);
    $field = $this->createField($valueFieldName)
      ->description($description)
      ->valueField()
      ->machineName($fieldName);

    if ($isMultiple) {
      $field->list();
    }
    $fieldType = $fieldDefinition->getType();

    if (
      $typedData instanceof StringItem ||
      $typedData instanceof StringItemBase ||
      $typedData instanceof EmailItem ||
      $typedData instanceof ListStringItem ||
      $fieldType === 'telephone'
    ) {
      return $field->type('String');
    }
    elseif ($typedData instanceof LanguageItem) {
      return $field->type('LanguageInterface');
    }
    elseif ($typedData instanceof IntegerItem) {
      return $field->type('Int');
    }
    elseif ($typedData instanceof NumericItemBase) {
      return $field->type('Float');
    }
    elseif ($typedData instanceof BooleanItem) {
      return $field->type('Boolean');
    }
    elseif ($typedData instanceof EntityReferenceItem && !$typedData instanceof FileItem) {
      $type = $this->getTypeForEntityReferenceFieldItem($itemDefinition, $isMultiple);
      if ($type) {
        return $field->type($type);
      }
      // The entity type that is referenced is not enabled, so we don't output
      // this field at all.
      return NULL;
    }
    elseif ($typedData instanceof TextWithSummaryItem) {
      $summary = $this->createField('summary')->type('Boolean');
      return $field->type('String')->argument($summary);
    }
    elseif ($typedData instanceof TextItemBase) {
      return $field->type('String');
    }
    elseif ($typedData instanceof TimestampItem) {
      return $field->type('String');
    }
    elseif ($typedData instanceof MapItem) {
      return $field->type('MapData');
    }

    // The field type is not scalar, try to get the GraphQL type for this item
    // definition.
    $itemType = $this->getFieldItemType($itemDefinition);
    if ($itemType) {
      return $field->type($itemType);
    }

    return NULL;
  }

  /**
   * Generate the GraphQL type for an entity reference field item.
   *
   * @param \Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface $itemDefinition
   *   The field definition.
   *
   * @return string|null
   *   The name of the referenced type.
   */
  private function getTypeForEntityReferenceFieldItem(FieldItemDataDefinitionInterface $itemDefinition): ?string {
    $targetType = $itemDefinition->getSetting('target_type');

    // Check if the target entity type is enabled.
    if (!$this->config->isEntityTypeEnabled($targetType)) {
      return NULL;
    }

    // Get the target bundles that can be referenced. This value is a bit
    // random, either a string or an array.
    $handlerSettings = $itemDefinition->getSetting('handler_settings') ?? [];
    $targetBundles = $handlerSettings['target_bundles'] ?? [];
    if (is_string($targetBundles)) {
      $targetBundles = [$targetBundles];
    }
    $targetBundles = array_values($targetBundles);

    // Handle case where target bundles have been defined.
    if (!empty($targetBundles)) {
      // Any or more than 1 target bundles allowed. The field type will be the
      // entity type.
      if (count($targetBundles) > 1) {
        return EntitySchemaHelper::toPascalCase([$targetType]);
      }

      // If the target bundle is the same as the type we ignore it.
      if ($targetType !== $targetBundles[0]) {
        // Only a single bundle is allowed. The field type will be this specific
        // bundle.
        return EntitySchemaHelper::toPascalCase([
          $targetType,
          $targetBundles[0],
        ]);
      }
    }

    if ($targetType) {
      return EntitySchemaHelper::toPascalCase([$targetType]);
    }

    return NULL;
  }

  /**
   * Build the FieldItemList type for a field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return string|null
   *   The GraphQL type.
   */
  private function getFieldItemListType(FieldDefinitionInterface $fieldDefinition): string|null {
    // e.g. string, boolean, email, string_long, text_with_summary.
    $fieldTypeName = $fieldDefinition->getType();

    // e.g. FieldItemListEmail.
    $graphqlTypeName = EntitySchemaHelper::toPascalCase([
      'field_item_list_',
      $fieldTypeName,
    ]);

    // Type was already generated.
    if ($this->registry->typeWillExist($graphqlTypeName)) {
      return $graphqlTypeName;
    }

    $type = $this->registry
      ->createType($graphqlTypeName)
      ->description((string) $fieldDefinition->getLabel())
      ->addInterface('FieldItemList');

    $itemDefinition = $fieldDefinition->getItemDefinition();
    if ($itemDefinition instanceof FieldItemDataDefinition) {
      $fieldItemType = $this->getFieldItemType($itemDefinition);
      if ($fieldItemType) {
        $list = $this->createField('list')->type($fieldItemType)->description('Array of field items.')->list();
        $type->addField($list);
        $first = $this->createField('first')->type($fieldItemType)->description('The first field item.');
        $type->addField($first);
      }
    }

    return $graphqlTypeName;
  }

  /**
   * Check if the given entity reference field should be added.
   */
  private function shouldAddField(string $fieldName, FieldDefinitionInterface $definition) {
    if (in_array($fieldName, self::EXCLUDED_ENTITY_FIELDS)) {
      return FALSE;
    }
    $fieldType = $definition->getType();
    if (in_array($fieldType, self::EXCLUDED_TYPES)) {
      return FALSE;
    }
    if ($fieldType === 'entity_reference' || $fieldType === 'entity_reference_revisions') {
      $targetType = $definition->getSetting('target_type');
      return $this->config->isEntityTypeEnabled($targetType);
    }

    return TRUE;
  }

  /**
   * Build the type for a field item definition.
   *
   * @param \Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface $itemDefinition
   *   The field item data definition.
   *
   * @return string|null
   *   The GraphQL type if available.
   */
  private function getFieldItemType(FieldItemDataDefinitionInterface $itemDefinition): string|null {
    $fieldDefinition = $itemDefinition->getFieldDefinition();
    // The type, e.g. string, text_with_summary, email, telephone.
    $type = $fieldDefinition->getType();

    // e.g. FielditemTypeTextWithSummary.
    $graphqlDataTypeName = EntitySchemaHelper::toPascalCase(
      ['field_item_type_', $type]
    );

    // Type has already been generated.
    if ($this->registry->typeWillExist($graphqlDataTypeName)) {
      return $graphqlDataTypeName;
    }

    $type = $this->registry
      ->createType($graphqlDataTypeName)
      ->addInterface('FieldItemType')
      ->description((string) $itemDefinition->getLabel());

    $propertyDefinitions = $itemDefinition->getPropertyDefinitions();

    // Is set to TRUE if there is a "value" property of type "string".
    $hasStringValue = FALSE;
    // Is set to TRUE if there is a "value" property of type "integer".
    $hasIntegerValue = FALSE;

    foreach ($propertyDefinitions as $name => $propertyDefinition) {
      if ($propertyDefinition instanceof DataDefinition) {
        $propertyFieldType = $this->getDataPropertyType($propertyDefinition->toArray());
        // Field item types that share the same value field with the same type
        // all get an additional interface.
        if ($name === 'value') {
          if ($propertyFieldType === 'String') {
            $hasStringValue = TRUE;
          }
          elseif ($propertyFieldType === 'Int') {
            $hasIntegerValue = TRUE;
          }
        }
        if ($propertyFieldType) {
          $propertyFieldName = EntitySchemaHelper::toCamelCase($name);
          // If a field with the same name has alrady been generated, use the
          // original Drupal name instead. The conversion from snake to camel
          // case can result in two snake case field names having the same
          // camel case string.
          if (!empty($fields[$propertyFieldName])) {
            $propertyFieldName = $name;
          }
          $description = (string) $propertyDefinition->getLabel();
          $field = $this
            ->createField($propertyFieldName)
            ->type($propertyFieldType)
            ->machineName($name)
            ->description($description);
          $type->addField($field);
        }
      }
    }

    // Field item types are always generated, even if no fields have been
    // derived. This is so that schema extensions can easily extend these types
    // by implementing missing fields.
    $typedData = $this->getTypedDataManager()->create($itemDefinition);

    // Add additional interfaces for certain field item types.
    // Interface for timestamp/date field items.
    if ($typedData instanceof TimestampItem || $typedData instanceof DateTimeItem) {
      $type->addInterface('FieldItemTypeTimestampInterface');
    }

    // Interface for field item types whose "value" field is a string.
    if ($hasStringValue) {
      $type->addInterface('FieldItemTypeStringInterface');
    }

    // Interface for field item types whose "value" field is an integer.
    if ($hasIntegerValue) {
      $type->addInterface('FieldItemTypeIntegerInterface');
    }

    return $graphqlDataTypeName;
  }

  /**
   * Add types for the entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fieldDefinitions
   *   The base field definitions of the entity type.
   *
   * @return string
   *   The name of the generated type or interface.
   */
  public function addContentEntityType(EntityTypeInterface $entityType, array $fieldDefinitions): string {
    $typeName = EntitySchemaHelper::toPascalCase([$entityType->id()]);
    if ($this->registry->typeWillExist($typeName)) {
      return $typeName;
    }
    $this->registry->addGeneratedTypeName($typeName);
    $hasBundles = $entityType->hasKey('bundle');
    $description = (string) $entityType->getLabel();

    $interfaces = $this->getInterfacesForEntityType($entityType);
    $fields = $this->createEntityFields($entityType->id(), $fieldDefinitions);

    if ($hasBundles) {
      $this->registry->createOrExtendInterface($typeName, $description, $fields, $interfaces);
      return $typeName;
    }
    $type = $this->registry->createType($typeName)->description($description);

    foreach ($interfaces as $interface) {
      $type->addInterface($interface);
    }
    foreach ($fields as $field) {
      $type->addField($field);
    }

    return $typeName;
  }

  /**
   * Generate a GraphQL type for an entity type.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return string|null
   *   The generated GraphQL type.
   */
  public function generateTypeForEntityType(string $entityTypeId): string|null {
    // Don't generate types for disabled entity types.
    if (!$this->config->isEntityTypeEnabled($entityTypeId)) {
      return NULL;
    }

    $graphqlTypeName = EntitySchemaHelper::toPascalCase($entityTypeId);
    if ($this->registry->typeWillExist($graphqlTypeName)) {
      return $graphqlTypeName;
    }

    $entityType = $this->getEntityTypeManager()->getDefinition($entityTypeId);

    if (!$entityType) {
      return NULL;
    }

    if ($entityType instanceof ConfigEntityTypeInterface) {
      $mapping = $this->getConfigEntityMapping($entityType);
      return $this->addConfigEntityType($entityType, $mapping);
    }
    else {
      $hasBundles = $entityType->hasKey('bundle');
      $fieldDefinitions = $hasBundles
          ? $this->getEntityFieldManager()->getBaseFieldDefinitions($entityTypeId)
          : $this->getEntityFieldManager()->getFieldDefinitions($entityTypeId, $entityTypeId);

      $generatedType = $this->addContentEntityType($entityType, $fieldDefinitions);

      if ($generatedType && $hasBundles) {
        $bundles = $this->getEntityTypeBundleInfo()->getBundleInfo($entityTypeId);
        foreach (array_keys($bundles) as $bundleId) {
          $bundleFieldDefinitions = $this->getEntityFieldManager()->getFieldDefinitions($entityTypeId, $bundleId);
          $this->addContentEntityBundleType($entityType, $bundleId, $bundles[$bundleId], $bundleFieldDefinitions);
        }
      }

      return $generatedType;
    }

    return NULL;
  }

  /**
   * Add types for the configuration entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityType $entityType
   *   The config entity type ID.
   * @param array $mapping
   *   The schema mapping for the config entity.
   *
   * @return string|null
   *   The name of the generated GraphQL type.
   */
  public function addConfigEntityType(ConfigEntityType $entityType, array $mapping): string {
    $entityTypeId = $entityType->id();
    $typeName = EntitySchemaHelper::toPascalCase([$entityTypeId]);
    if ($this->registry->typeWillExist($typeName)) {
      return $typeName;
    }
    $type = $this->registry->createType($typeName)->description($entityType->getLabel());
    $type->addInterface('Entity');
    $fields = [];

    foreach ($mapping as $propertyName => $definition) {
      $graphqlFieldName = EntitySchemaHelper::toCamelCase($propertyName);
      if (in_array($propertyName, self::EXCLUDED_ENTITY_FIELDS)) {
        continue;
      }
      if (!$this->config->fieldIsEnabled($entityTypeId, $propertyName)) {
        continue;
      }

      $propertyType = $this->getDataPropertyType($definition);
      if ($propertyType) {
        if (!empty($fields[$graphqlFieldName])) {
          $graphqlFieldName = $propertyName;
        }
        $fields[] = $graphqlFieldName;
        $field = $this
          ->createField($graphqlFieldName)
          ->machineName($propertyName)
          ->type($propertyType);
        $type->addField($field);
      }
    }

    if ($entityTypeId === 'configurable_language') {
      $type->addInterface('LanguageInterface');
    }

    return $typeName;
  }

  /**
   * Add types for the entity bundle type.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   The entity type.
   * @param string $bundleId
   *   The bundle ID.
   * @param array $bundleInfo
   *   The bundle info.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fieldDefinitions
   *   The field definitions of the bundle.
   */
  public function addContentEntityBundleType(ContentEntityTypeInterface $entityType, string $bundleId, array $bundleInfo, array $fieldDefinitions) {
    $entityTypeId = $entityType->id();
    $entityTypeName = EntitySchemaHelper::toPascalCase([$entityTypeId]);
    $bundleTypeName = EntitySchemaHelper::toPascalCase(
      [$entityTypeId, $bundleId]
    );

    $description = (string) $bundleInfo['label'] ?? $entityTypeId;
    $type = $this->registry->createType($bundleTypeName)->description($description);
    $type->addInterface($entityTypeName);

    foreach ($this->getInterfacesForEntityType($entityType) as $interface) {
      $type->addInterface($interface);
    }

    foreach ($this->createEntityFields($entityType->id(), $fieldDefinitions) as $field) {
      $type->addField($field);
    }

    // Add the interface and fields for a translatable entity.
    // We do this separately so that we can use the entity's
    // type as the type for both fields.
    if (!empty($bundleInfo['translatable'])) {
      $type->addInterface('EntityTranslatable');
    }
  }

  /**
   * Get interfaces for the entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return string[]
   *   The interfaces.
   */
  public function getInterfacesForEntityType(EntityTypeInterface $entityType): array {
    $pairs = [
      '\Drupal\Core\Entity\EntityDescriptionInterface' => 'EntityDescribable',
    ];

    $interfaces = [
      'Entity',
    ];

    foreach ($pairs as $dependency => $interface) {
      if ($entityType->entityClassImplements($dependency)) {
        $interfaces[] = $interface;
      }
    }

    $isLinkable = !empty($entityType->getLinkTemplates());
    if ($isLinkable) {
      $interfaces[] = 'EntityLinkable';
    }

    if ($entityType->isRevisionable()) {
      $interfaces[] = 'EntityRevisionable';
    }

    return $interfaces;
  }

  /**
   * Merge fields from the given interfaces with the base fields.
   *
   * @param array $fields
   *   The type fields.
   * @param \GraphQL\Type\Definition\InterfaceType[] $interfaces
   *   The interfaces.
   *
   * @return array
   *   The type fields merged with the interface fields.
   */
  public function mergeInterfaceFields(array $fields, array $interfaces): array {
    $mergedFields = $fields;

    foreach ($interfaces as $interface) {
      $mergedFields = array_merge($mergedFields, $interface->getFields());
    }

    return $mergedFields;
  }

  /**
   * Create GraphQL fields given the entity field definitions.
   *
   * @param string $entityTypeId
   *   The entity type ID the fields belong to.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fieldDefinitions
   *   The field definitions.
   *
   * @return SchemaBuilderField[]
   *   The array of GraphQL fields.
   */
  private function createEntityFields(string $entityTypeId, array $fieldDefinitions): array {
    $fields = [];
    foreach ($fieldDefinitions as $fieldName => $definition) {
      // Try to get the type first. This way we can make sure that we generate
      // a type for every field type, even if no entity has a field with that
      // type.
      $type = $this->getFieldItemListType($definition);

      if (!$this->config->fieldIsEnabled($entityTypeId, $fieldName)) {
        continue;
      }
      if (!$this->shouldAddField($fieldName, $definition)) {
        continue;
      }

      if ($type) {
        $graphqlFieldName = EntitySchemaHelper::toCamelCase(
          [$fieldName, '_raw_field']
        );
        if (!empty($fields[$graphqlFieldName])) {
          $graphqlFieldName = $fieldName . 'RawField';
        }
        $description = (string) $definition->getDescription();
        $fields[$graphqlFieldName] = $this->createField($graphqlFieldName)
          ->type($type)
          ->description($description)
          ->machineName($fieldName);
      }
      if ($this->config->shouldGeneratedValueFields()) {
        $valueField = $this->buildGraphqlValueField($definition);
        if ($valueField) {
          $valueFieldName = $valueField->getName();
          // If there has already been a field created with this name there is
          // a conflict between field names that have been camel cased. In
          // this rare case we generate the field name using the actual Drupal
          // machine name.
          if (!empty($fields[$valueFieldName])) {
            $valueFieldName = $fieldName;
          }
          $fields[$valueFieldName] = $valueField;
        }
      }
    }

    return $fields;
  }

  /**
   * Get the GraphQL type for a data property definition.
   *
   * This is the lowest possible leaf in the entity schema. It usually resolves
   * to a scalar, but special handling is implemented for sequence types and
   * entity reference types.
   *
   * @param array $definition
   *   The property definition.
   *
   * @return string|null
   *   The name of the GraphQL type.
   */
  protected function getDataPropertyType(array $definition): string|null {
    $type = $definition['type'];

    if (in_array($type, self::EXCLUDED_TYPES)) {
      return NULL;
    }

    // Basic types.
    switch ($type) {
      case 'string':
      case 'email':
      case 'text':
      case 'label':
      case 'path':
      case 'color_hex':
      case 'date_form':
      case 'filter_format':
      case 'datetime_iso8601':
      case 'timestamp':
      case 'required_label':
      case 'machine_name':
        return 'String';

      case 'boolean':
        return 'Boolean';

      case 'integer':
        return 'Int';

      case 'float':
        return 'Float';

      case 'uri':
        return 'Url';

      case 'config_dependencies':
        return 'MapData';

      case '_core_config_info':
        return NULL;

      case 'entity_reference':
      case 'entity_revision_reference':
        $targetEntityType = $definition['constraints']['EntityType'] ?? NULL;
        if ($targetEntityType) {
          $generatedType = $this->generateTypeForEntityType($targetEntityType);
          if ($generatedType) {
            return $generatedType;
          }
        }
        return 'Entity';

      case 'language_reference':
        return 'LanguageInterface';
    }

    // Try to find a matching data definition for this type.
    if ($this->getTypedDataManager()->hasDefinition($type)) {
      $dataDefinition = $this->getTypedDataManager()->getDefinition($type);
      $instance = $this->getTypedDataManager()->createDataDefinition($type);
      $typedData = $this->getTypedDataManager()->create($instance);

      if ($typedData instanceof StringData) {
        return 'String';
      }
      if ($instance instanceof ComplexDataDefinitionInterface) {
        $propertyDefinitions = $instance->getPropertyDefinitions();
        return $this->getComplexDataType($type, $propertyDefinitions);
      }
    }
    elseif ($this->getTypedConfigManager()->hasDefinition($type)) {
      $dataDefinition = $this->getTypedConfigManager()->getDefinition($type);
      $instance = $this->getTypedConfigManager()->createDataDefinition($type);

      // A mapping is basically a type that references another type.
      // The method being called here will eventually call this method again. If
      // the referenced map type again references a map type, it might end up
      // here a third time and so on. In the end we have eventually resolved to a
      // scalar type being returned above.
      // This allows us to fully resolve config schema types down to the last
      // property, if supported.
      if ($dataDefinition && $instance) {
        if (!empty($dataDefinition['mapping'])) {
          return $this->getTypeForMapping($type, $dataDefinition['mapping']);
        }
      }
    }

    return NULL;
  }

  /**
   * Try to infer the type for a mapping property.
   *
   * @param string $mappingName
   *   The name of the mapping.
   * @param array $mapping
   *   The mapping configuration.
   *
   * @return string|null
   *   The GraphQL type if found.
   */
  private function getTypeForMapping(string $mappingName, array $mapping): string|null {
    // E.g. DataTypeLinkitMatcher.
    $graphqlTypeName = $this->getGraphqlTypeNameForMapping($mappingName);

    // Type already generated.
    if ($this->registry->typeWillExist($graphqlTypeName)) {
      return $graphqlTypeName;
    }

    $type = $this->registry->createType($graphqlTypeName)->description("The $mappingName schema mapping.");

    foreach ($mapping as $mappingProperty => $mappingDefinition) {
      $mappingType = $this->getDataPropertyType($mappingDefinition);
      if ($mappingType) {
        $field = $this->createField($mappingProperty)->type($mappingType);
        $type->addField($field);
      }
    }

    return $graphqlTypeName;
  }

  /**
   * Try to generate a type for ComplexDataDefinition with properties.
   *
   * @param string $typeName
   *   The name of the complex data.
   * @param \Drupal\Core\TypedData\DataDefinition[] $propertyDefinitions
   *   The property defintions.
   *
   * @return string|null
   *   The GraphQL type.
   */
  private function getComplexDataType(string $typeName, array $propertyDefinitions): string|null {
    // e.g. DataTypeShipmentItem.
    $graphqlDataTypeName = EntitySchemaHelper::toPascalCase(
      ['data_type_', $typeName]
    );

    // Type has already been generated.
    if ($this->registry->typeWillExist($graphqlDataTypeName)) {
      return $graphqlDataTypeName;
    }

    $fields = [];

    foreach ($propertyDefinitions as $name => $propertyDefinition) {
      if ($propertyDefinition instanceof DataDefinition) {
        $propertyFieldType = $this->getDataPropertyType($propertyDefinition->toArray());
        if ($propertyFieldType) {
          $propertyFieldName = EntitySchemaHelper::toCamelCase($name);
          $field = $this->createField($propertyFieldName)->type($propertyFieldType)->description((string) $propertyDefinition->getLabel());
          $fields[] = $field;
        }
      }
    }

    if (empty($fields)) {
      return 'MapData';
    }

    $type = $this->registry->createType($graphqlDataTypeName);

    foreach ($fields as $field) {
      $type->addField($field);
    }

    return $graphqlDataTypeName;
  }

  /**
   * Get the GraphQL type name for a schema type mapping.
   *
   * @param string $mappingName
   *   The name of the mapping.
   *
   * @return string
   *   The GraphQL type name.
   */
  protected function getGraphqlTypeNameForMapping(string $mappingName) {
    return EntitySchemaHelper::toPascalCase([
      'data_type_',
      $mappingName,
    ]);
  }

}
