<?php

namespace Drupal\graphql_core_schema\Form;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\graphql_core_schema\EntitySchemaBuilder;

/**
 * Helper class for the core_composable schema form.
 */
class CoreComposableSchemaFormHelper {

  use StringTranslationTrait;

  public const BASE_ENTITY_FIELD_DEFINITIONS = [
    'uuid' => 'The unique UUID.',
    'label' => 'The label of this entity.',
    'langcode' => 'The langcode of this entity.',
    'toArray' => 'Gets an array of all property values.',
    'getConfigTarget' => 'Gets the configuration target identifier for the entity.',
    'uriRelationships' => 'Gets a list of URI relationships supported by this entity.',
    'referencedEntities' => 'Gets a list of entities referenced by this entity.',
    'entityTypeId' => 'The entity type ID.',
    'entityBundle' => 'The bundle of the entity.',
    'isNew' => 'Determines whether the entity is new.',
    'accessCheck' => 'Check entity access for the given operation, defaults to view.',
  ];

  /**
   * Build the enabled field form.
   */
  public function buildEntityFieldForm(
    array &$form,
    FormStateInterface $form_state,
    array $configuration,
    array $enabledEntityTypes
  ) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = \Drupal::service('entity_type.manager');
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');

    $values = $form_state->getValues();
    // Use the form state to rebuild the options if there was an AJAX call.
    if (!empty($values['schema_configuration']['core_composable']['enabled_entity_types'])) {
      $entityTypes = Checkboxes::getCheckedCheckboxes($values['schema_configuration']['core_composable']['enabled_entity_types']);
    }
    else {
      $entityTypes = $enabledEntityTypes;
    }

    $form['fields'] = [
      '#prefix' => '<div id="field-wrapper">',
      '#suffix' => '</div>',
      '#type' => 'details',
      '#title' => $this->t('Enabled fields'),
    ];

    foreach ($entityTypes as $entityTypeId) {
      $entityType = $entityTypeManager->getDefinition($entityTypeId);
      if (!$entityType) {
        continue;
      }

      $form['fields'][$entityTypeId] = [
        '#type' => 'tableselect',
        '#caption' => $entityTypeId . ' (' . $entityType->getLabel() . ')',
        '#sticky' => TRUE,
        '#header' => [
          'machine_name' => $this->t('Machine name'),
          'label' => $this->t('Label'),
          'type' => $this->t('Type'),
          'description' => $this->t('Description'),
        ],
        '#options' => [],
        '#default_value' => $configuration['fields'][$entityTypeId] ?? [],
        '#empty' => $this->t('No fields available'),
        '#attributes' => [
          'class' => ['graphql-core-schema-field-table'],
        ],
      ];

      if ($entityType instanceof ConfigEntityTypeInterface) {
        $mapping = $this->getConfigEntityMapping($entityType);
        ksort($mapping);
        foreach ($mapping as $fieldName => $definition) {
          $type = $definition['type'] ?? '';
          if (!in_array($fieldName, EntitySchemaBuilder::EXCLUDED_ENTITY_FIELDS) && !in_array($type, EntitySchemaBuilder::EXCLUDED_TYPES)) {
            $form['fields'][$entityTypeId]['#options'][$fieldName] = [
              'machine_name' => $fieldName,
              'label' => $definition['label'] ?? '',
              'type' => $type,
              'description' => '',
            ];
          }
        }
      }
      else {
        /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $definitions */
        $definitions = [];

        $fieldDefinitions = $entityFieldManager->getBaseFieldDefinitions($entityTypeId);
        foreach ($fieldDefinitions as $fieldDefinition) {
          $definitions[$fieldDefinition->getName()] = $fieldDefinition;
        }

        $bundles = $entityTypeBundleInfo->getBundleInfo($entityTypeId);
        foreach (array_keys($bundles) as $bundleId) {
          $bundleFieldDefinitions = $entityFieldManager->getFieldDefinitions($entityTypeId, $bundleId);
          foreach ($bundleFieldDefinitions as $bundleFieldDefinition) {
            $fieldName = $bundleFieldDefinition->getName();
            if (empty($definitions[$fieldName])) {
              $definitions[$fieldName] = $bundleFieldDefinition;
            }
          }
        }

        ksort($definitions);

        foreach ($definitions as $fieldName => $definition) {
          $type = $definition->getType();
          if (!in_array($fieldName, EntitySchemaBuilder::EXCLUDED_ENTITY_FIELDS) && !in_array($type, EntitySchemaBuilder::EXCLUDED_TYPES)) {
            $form['fields'][$entityTypeId]['#options'][$fieldName] = [
              'machine_name' => $fieldName,
              'label' => $definition->getLabel(),
              'type' => $type,
              'description' => $definition->getFieldStorageDefinition()->getDescription(),
            ];
          }
        }
      }
    }
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
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager */
    $typedConfigManager = \Drupal::service('config.typed');
    $configPrefix = $type->getConfigPrefix();
    $typedConfigDefinition = $typedConfigManager->getDefinition($configPrefix . '.*');
    $mapping = $typedConfigDefinition['mapping'] ?? [];
    if (empty($mapping)) {
      $typedConfigDefinition = $typedConfigManager->getDefinition($configPrefix . '.*.*');
      $mapping = $typedConfigDefinition['mapping'] ?? [];
    }
    if (empty($mapping)) {
      $typedConfigDefinition = $typedConfigManager->getDefinition($configPrefix . '.*.*.*');
      $mapping = $typedConfigDefinition['mapping'] ?? [];
    }

    return $mapping;
  }

  /**
   * Build the configuration form.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $formState
   *   The form state.
   * @param array $configuration
   *   The configuration.
   * @param \Drupal\graphql\Plugin\SchemaExtensionPluginInterface[] $extensions
   *   The extensions.
   * @param string $ajaxCallback
   */
  public function buildConfigurationForm(&$form, FormStateInterface $formState, array $configuration, array $extensions, $ajaxCallback) {
    // Sort list of extensions alphabetically.
    ksort($form['extensions']['#options']);

    $form['generate_value_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable value fields'),
      '#description' => $this->t('Value fields directly return a scalar or entity type instead of a FieldItemList type.'),
      '#default_value' => $configuration['generate_value_fields'] ?? FALSE,
    ];

    foreach ($extensions as $extension) {
      if ($extension instanceof PluginFormInterface) {
        $subformKey = 'extension_' . $extension->getBaseId();
        $form[$subformKey] = [
          '#type' => 'details',
          '#title' => $extension->getPluginDefinition()['name'],
        ];
        $subform_state = SubformState::createForSubform($form[$subformKey], $form, $formState);
        $form[$subformKey] = $extension->buildConfigurationForm($form[$subformKey], $subform_state);
      }
    }

    $form['entity_base_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled entity base fields'),
    ];

    $form['entity_base_fields']['fields'] = [
      '#type' => 'tableselect',
      '#sticky' => TRUE,
      '#caption' => $this->t('Select the fields you want to enable on all entity types.'),
      '#header' => [
        'name' => $this->t('Field'),
        'description' => $this->t('Description'),
      ],
      '#options' => [],
      '#default_value' => $configuration['entity_base_fields']['fields'] ?? [],
    ];

    foreach (self::BASE_ENTITY_FIELD_DEFINITIONS as $key => $description) {
      $form['entity_base_fields']['fields']['#options'][$key] = [
        'name' => $key,
        'description' => $description,
      ];
    }

    $entityTypeDefintions = \Drupal::entityTypeManager()->getDefinitions();
    ksort($entityTypeDefintions);

    $form['enabled_entity_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled entity types'),
    ];

    foreach ($entityTypeDefintions as $key => $type) {
      $label = $type->getLabel();
      $form['enabled_entity_types'][$key] = [
        '#id' => $key,
        '#type' => 'checkbox',
        '#title' => $key . " ($label)",
        '#default_value' => $configuration['enabled_entity_types'][$key] ?? FALSE,
        '#ajax' => [
          'callback' => $ajaxCallback,
          'disable-refocus' => FALSE,
          'event' => 'change',
          'wrapper' => 'field-wrapper',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Reloading fields...'),
          ],
        ],
      ];
    }
  }

}
