<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\Schema;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\Schema\ComposableSchema;
use Drupal\graphql\Plugin\SchemaExtensionPluginInterface;
use Drupal\graphql\Plugin\SchemaExtensionPluginManager;
use Drupal\graphql_core_schema\CoreComposableConfig;
use Drupal\graphql_core_schema\CoreComposableResolver;
use Drupal\graphql_core_schema\CoreSchemaExtensionInterface;
use Drupal\graphql_core_schema\CoreSchemaInterfaceExtensionInterface;
use Drupal\graphql_core_schema\EntitySchemaBuilder;
use Drupal\graphql_core_schema\Form\CoreComposableSchemaFormHelper;
use Drupal\graphql_core_schema\GraphQL\Enums\DrupalDateFormatEnum;
use Drupal\graphql_core_schema\GraphQL\Enums\EntityTypeEnum;
use Drupal\graphql_core_schema\GraphQL\Enums\LangcodeEnum;
use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderGenerator;
use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderRegistry;
use Drupal\graphql_core_schema\TypeAwareSchemaExtensionInterface;
use Drupal\typed_data\DataFetcherTrait;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extendable core schema.
 *
 * @Schema(
 *   id = "core_composable",
 *   name = "Core Composable Schema"
 * )
 */
class CoreComposableSchema extends ComposableSchema {

  use TypedDataTrait;
  use DataFetcherTrait;
  use DependencySerializationTrait;

  /**
   * Array of generated GraphQL types.
   *
   * The types are only present if the schema is being generated. In a
   * normal production environment this is empty, because it's only needed
   * when the schema is extended.
   *
   * @var string[]
   */
  protected $generatedTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.graphql.ast'),
      $container->get('module_handler'),
      $container->get('plugin.manager.graphql.schema_extension'),
      $container->getParameter('graphql.config'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * The constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param array $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\Cache\CacheBackendInterface $astCache
   *   The cache bin for caching the parsed SDL.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\graphql\Plugin\SchemaExtensionPluginManager $extensionManager
   *   The schema extension plugin manager.
   * @param array $config
   *   The service configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param FileSystem $fileSystem
   *   The file system service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    array $pluginDefinition,
    CacheBackendInterface $astCache,
    ModuleHandlerInterface $moduleHandler,
    SchemaExtensionPluginManager $extensionManager,
    array $config,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $astCache, $moduleHandler, $extensionManager, $config);
  }

  /**
   * Get the core schema definitions.
   *
   * @param string[] $extensionBaseDefinitions
   *   The base definition files from enabled extensions.
   *
   * @return \GraphQL\Language\AST\DefinitionNode[]
   *   The core schema definitions.
   */
  protected function getCoreSchemaDefinition(array $extensionBaseDefinitions): array {
    $module = $this->moduleHandler->getModule('graphql_core_schema');
    $folder = $module->getPath() . '/graphql/core';
    $files = $this->fileSystem->scanDirectory($folder, '/.*\.graphqls$/');

    $coreFiles = array_map(function ($file) {
      return file_get_contents($file->uri);
    }, $files);

    $sdl = implode("\n\n", array_merge($coreFiles, $extensionBaseDefinitions));
    $parsed = Parser::parse($sdl);
    return iterator_to_array($parsed->definitions);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSchemaDefinition(): string {
    $config = CoreComposableConfig::fromConfiguration($this->configuration);

    // Throw an exception here because the schema is broken if no entity type
    // is enabled.
    if (empty($config->getEnabledEntityTypes())) {
      throw new \Exception('At least one entity type must be enabled for the schema to work properly.');
    }

    $extensions = $this->getExtensions();
    $extensionBaseDefinitions = [];

    foreach ($extensions as $extension) {
      $extensionBaseDefinitions[] = $extension->getBaseDefinition();
      if ($extension instanceof CoreSchemaInterfaceExtensionInterface) {
        $extensionId = $extension->getPluginId();
        throw new \Exception("Extending interfaces using getInterfaceExtender() has been removed. You can now directly define the interface in $extensionId.base.graphqls, it will be used as the base for generating the interface. More information: https://graphql-core-schema.netlify.app/advanced/extending-interfaces.html#defining-the-interface-in-an-extension-base-graphqls-file");
      }
    }

    $coreSchemaDefintions = $this->getCoreSchemaDefinition($extensionBaseDefinitions);
    $entityTypeDefintions = $this->entityTypeManager->getDefinitions();
    $schemaBuilderRegistry = new SchemaBuilderRegistry();
    $schemaBuilder = new EntitySchemaBuilder(
      $schemaBuilderRegistry,
      $config,
    );

    foreach (array_keys($entityTypeDefintions) as $typeId) {
      $schemaBuilder->generateTypeForEntityType($typeId);
    }

    $generator = new SchemaBuilderGenerator();
    $generator
      ->addType(new LangcodeEnum())
      ->addType(new DrupalDateFormatEnum())
      ->addType(new EntityTypeEnum($config->getEnabledEntityTypes()));

    $schema = $generator->getGeneratedSchema($schemaBuilderRegistry, $config, $coreSchemaDefintions);
    $this->generatedTypes = $generator->getGeneratedTypeNames();
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensions() {
    $extensions = array_map(function ($id) {
      $extensionConfiguration = $this->configuration['extension_' . $id] ?? [];
      if ($this->extensionManager->hasDefinition($id)) {
        return $this->extensionManager->createInstance($id, $extensionConfiguration);
      }
    }, array_filter($this->getConfiguration()['extensions'] ?? []));

    return array_filter($extensions);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = CoreComposableConfig::fromConfiguration($this->configuration);
    $formHelper = new CoreComposableSchemaFormHelper();
    $formHelper->buildConfigurationForm(
      $form,
      $form_state,
      $this->configuration,
      $this->getExtensions(),
      [$this, 'reloadFields']
    );
    $formHelper->buildEntityFieldForm($form, $form_state, $this->configuration, $config->getEnabledEntityTypes());

    $form['#attached']['library'][] = 'graphql_core_schema/tweaks';
    return $form;
  }

  /**
   * Ajax Callback for form reload.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function reloadFields(array &$form, FormStateInterface $form_state) {
    return $form['schema_configuration']['core_composable']['fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    $values = $formState->getValues();
    $extensions = array_filter(array_values($formState->getValue('extensions')));
    $entityTypesArray = is_array($values['enabled_entity_types']) ? $values['enabled_entity_types'] : [];
    $entityTypes = Checkboxes::getCheckedCheckboxes($entityTypesArray);

    foreach ($extensions as $extensionId) {
      $instance = $this->extensionManager->createInstance($extensionId);
      if ($instance instanceof CoreSchemaExtensionInterface) {
        $requiredEntityIds = $instance->getEntityTypeDependencies();
        foreach ($requiredEntityIds as $entityId) {
          if (!in_array($entityId, $entityTypes)) {
            $element = $form['enabled_entity_types'][$entityId];
            $formState->setError(
              $element,
              $this->t('Extension "@extension" requires entity type "@type" to be enabled', [
                '@extension' => $instance->getBaseId(),
                '@type' => $entityId,
              ]
            ));
          }
        }

        $requiredExtensions = $instance->getExtensionDependencies();
        foreach ($requiredExtensions as $requiredExtensionId) {
          if (!in_array($requiredExtensionId, $extensions)) {
            $formState->setErrorByName(
              $requiredExtensionId . '_' . $extensionId,
              $this->t('Extension "@extension" requires extension "@dependency" to be enabled', [
                '@extension' => $instance->getBaseId(),
                '@dependency' => $requiredExtensionId,
              ]
            ));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResolverRegistry() {
    // Create the registry and provide our default field and type resolvers.
    // As the name suggests these are called if no other field or type resolver
    // matched. This means that, to "override" the behavior of a field you can
    // just register your own resolver for this specific field.
    $registry = new ResolverRegistry(
      [CoreComposableResolver::class, 'resolveFieldDefault'],
      [CoreComposableResolver::class, 'resolveTypeDefault'],
    );

    $builder = new ResolverBuilder();
    CoreComposableResolver::registerPingResolvers($registry, $builder);
    CoreComposableResolver::registerEntityResolvers($registry, $builder);
    CoreComposableResolver::registerFieldListResolvers($registry, $builder);
    CoreComposableResolver::registerLanguageResolvers($registry, $builder);
    CoreComposableResolver::registerUrlResolvers($registry, $builder);
    return $registry;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema(ResolverRegistryInterface $registry) {
    $extensions = $this->getExtensions();
    $resolver = [$registry, 'resolveType'];
    $document = $this->getSchemaDocument($extensions);

    // Performance optimization.
    // Do not validate the schema on every request by passing the option: ['assumeValid' => true] to the build function.
    $options = ['assumeValid' => TRUE];

    $schema = BuildSchema::build($document, function ($config, TypeDefinitionNode $type) use ($resolver) {
      if ($type instanceof InterfaceTypeDefinitionNode || $type instanceof UnionTypeDefinitionNode) {
        $config['resolveType'] = $resolver;
      }

      return $config;
    }, $options);

    if (empty($extensions)) {
      return $schema;
    }

    foreach ($extensions as $extension) {
      $extension->registerResolvers($registry);
    }

    if ($extendSchema = $this->getExtensionDocument($extensions)) {
      // Generate the AST from the extended schema and save it to the cache.
      // This is important, because the Drupal graphql module is not caching the extended schema.
      // During schema extension, a very expensive function \GraphQL\Type\Schema::getTypeMap() is called.
      // Caching the AST of the extended schema improved greatly the performance.
      // This process will remove all directives, as this is still not supported by the SchemaPrinter.
      // See https://github.com/webonyx/graphql-php/issues/552
      $document = $this->getExtensionSchemaAst($schema, $extendSchema);

      $options = ['assumeValid' => TRUE];
      $extended_schema = BuildSchema::build($document, function ($config, TypeDefinitionNode $type) use ($resolver) {
        if ($type instanceof InterfaceTypeDefinitionNode || $type instanceof UnionTypeDefinitionNode) {
          $config['resolveType'] = $resolver;
        }
        return $config;
      }, $options);
      return $extended_schema;
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaDocument(array $extensions = []) {
    // @todo Remove this function as soon as
    // https://github.com/drupal-graphql/graphql/pull/1314
    // is merged.
    $cid = "schema:{$this->getPluginId()}";
    if (empty($this->inDevelopment) && $cache = $this->astCache->get($cid)) {
      return $cache->data;
    }

    $schema = [$this->getSchemaDefinition()];

    // This option avoids WSOD / recursion issues.
    $options = ['noLocation' => TRUE];
    $ast = Parser::parse(implode("\n\n", $schema), $options);
    if (empty($this->inDevelopment)) {
      $this->astCache->set($cid, $ast, CacheBackendInterface::CACHE_PERMANENT, ['graphql']);
    }

    return $ast;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensionDocument(array $extensions = []) {
    // Only use caching of the parsed document if we aren't in development mode.
    $cid = "extension:{$this->getPluginId()}";
    if (empty($this->inDevelopment) && $cache = $this->astCache->get($cid)) {
      return $cache->data;
    }

    $extensions = array_filter(array_map(function (SchemaExtensionPluginInterface $extension) {
      $extensionSchema = $extension->getExtensionDefinition();

      // Extensions implementing this interface can additionally extend the
      // schema conditionally. They get an array of all generated GraphQL types
      // as the first argument.
      if ($extension instanceof TypeAwareSchemaExtensionInterface) {
        $typeExtensionSchema = $extension->getTypeExtensionDefinition($this->generatedTypes ?? []);
        if ($typeExtensionSchema) {
          $extensionSchema .= "\n\n" . $typeExtensionSchema;
        }
      }

      return $extensionSchema;
    }, $extensions), function ($definition) {
      return !empty($definition);
    });

    $ast = !empty($extensions) ? Parser::parse(implode("\n\n", $extensions)) : NULL;
    if (empty($this->inDevelopment)) {
      $this->astCache->set($cid, $ast, CacheBackendInterface::CACHE_PERMANENT, ['graphql']);
    }

    return $ast;
  }

  /**
   * Get the AST from an extension.
   *
   * @param \GraphQL\Type\Schema $schema
   *   The base schema.
   * @param \GraphQL\Language\AST\DocumentNode $extendSchema
   *   The extension schema.
   *
   * @return \GraphQL\Language\AST\DocumentNode
   *   The AST of the schema.
   *
   * @throws \GraphQL\Error\Error
   * @throws \GraphQL\Error\SyntaxError
   */
  public function getExtensionSchemaAst(Schema $schema, DocumentNode $extendSchema) {
    $cid = "schema_extension:{$this->getPluginId()}";
    if (empty($this->inDevelopment) && $cache = $this->astCache->get($cid)) {
      return $cache->data;
    }

    $schema = SchemaExtender::extend($schema, $extendSchema);
    $schema_string = SchemaPrinter::doPrint($schema);
    $options = ['noLocation' => TRUE];
    $ast = Parser::parse($schema_string, $options);
    if (empty($this->inDevelopment)) {
      $this->astCache->set($cid, $ast, CacheBackendInterface::CACHE_PERMANENT, ['graphql']);
    }

    return $ast;
  }

}
