<?php

namespace Drupal\graphql_core_schema;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\Field\Plugin\Field\FieldType\EmailItem;
use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem;
use Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItemBase;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\Plugin\DataType\Timestamp;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerProxy;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;
use Drupal\text\TextProcessed;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\WrappingType;

/**
 * The core composable resolver class.
 */
class CoreComposableResolver {

  /**
   * Resolves a default value for a field.
   *
   * @param mixed $value
   *   The value.
   * @param mixed $args
   *   The arguments.
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   *   The context.
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The graphql resolver info.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *   The field context.
   *
   * @return mixed|null
   *   The result.
   */
  public static function resolveFieldDefault($value, $args, ResolveContext $context, ResolveInfo $info, RefinableCacheableDependencyInterface $field) {
    $returnType = $info->returnType;
    $isArrayType = $returnType instanceof ListOfType;

    $renderContext = new RenderContext();
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $result = $renderer->executeInRenderContext(
      $renderContext,
      fn () => self::resolveField($value, $args, $context, $info, $field)
    );

    if (!$renderContext->isEmpty()) {
      $context->addCacheableDependency($renderContext->pop());
    }

    // Set cache dependencies.
    if ($result instanceof CacheableDependencyInterface) {
      $context->addCacheableDependency($result);
    }
    elseif (is_array($result)) {
      foreach ($result as $resultItem) {
        if ($resultItem instanceof CacheableDependencyInterface) {
          $context->addCacheableDependency($resultItem);
        }
      }
    }

    // Get the current language from the context.
    // If no language is set, set it from the current language.
    $language = $field->getContextValue('language');
    if (!$language) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $field->setContextValue('language', $language);
    }
    $translated = self::translateResolvedValue($result, $language, $isArrayType);

    // Access check for the resolved result.
    // The resolveFieldDefault resolver does not perform any access checks
    // while for example resolving references. This is all done here.
    // It's important to note that this is NOT called when using custom
    // field resolvers (e.g. in schema extensions) return an entity.
    return self::filterAccessible($translated, $context);
  }

  /**
   * Resolves a default value for a field.
   *
   * @param mixed $value
   *   The value.
   * @param mixed $args
   *   The arguments.
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   *   The context.
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The graphql resolver info.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $field
   *   The field context.
   *
   * @return mixed|null
   *   The result.
   */
  private static function resolveField($value, $args, ResolveContext $context, ResolveInfo $info, RefinableCacheableDependencyInterface $field) {
    // Find out if this is a value field.
    $fieldDescription = $info->fieldDefinition->description ?? '';
    $isValueField = str_starts_with($fieldDescription, '{value}');

    // The GraphQL field name.
    $fieldName = $field->getFieldName();
    $returnType = $info->returnType;
    $isList = $returnType instanceof ListOfType;

    // The Drupal field name.
    $drupalFieldName = self::getDrupalFieldName($fieldName, $fieldDescription);

    // Handle value fields.
    if ($isValueField) {
      $results = self::resolveFieldValue($value, $drupalFieldName, $args);
      return $isList ? $results : $results[0] ?? NULL;
    }

    // Handle all other fields.
    if ($value instanceof EntityInterface) {
      if ($value instanceof FieldableEntityInterface || $value instanceof ConfigEntityInterface) {
        return $value->get($drupalFieldName) ?? $value->get($fieldName);
      }
    }
    elseif ($value instanceof FieldItemListInterface) {
      return iterator_to_array($value);
    }
    elseif ($value instanceof FieldItemInterface) {
      return self::resolveItem($value, $info, $drupalFieldName);
    }
    elseif (is_array($value) && isset($value[$drupalFieldName])) {
      return $value[$drupalFieldName];
    }

    // This default resolver will try to resolve the value based on the
    // GraphQL field name.
    return Executor::defaultFieldResolver($value, $args, $context, $info);
  }

  /**
   * Translate the resolved values.
   *
   * @param mixed $resolvedValue
   *   The resolved value.
   * @param string|null $language
   *   The target language.
   * @param bool $isArray
   *   If the resolved value is an array (in GraphQL terms).
   *
   * @return mixed
   *   The resolved value, translated.
   */
  private static function translateResolvedValue($resolvedValue, string $language, bool $isArray) {
    if ($isArray && is_array($resolvedValue)) {
      $translated = [];
      foreach ($resolvedValue as $item) {
        $translated[] = self::translateResolvedValue($item, $language, FALSE);
      }
      return $translated;
    }

    if ($resolvedValue instanceof TranslatableInterface) {
      if ($resolvedValue->hasTranslation($language)) {
        return $resolvedValue->getTranslation($language);
      }
    }

    return $resolvedValue;
  }

  /**
   * Filter the input to only return accessible values.
   *
   * If the input is an AccessibleInterface object the method returns
   * either the object or NULL.
   * If the input is an array, it will iterate over the values and perform the
   * check if the values are AccessibleInterface objects. Those that fail the
   * check will be replaced with NULL.
   * Any other inputs are returned as is.
   *
   * @param mixed $value
   *   The input value, either object or an array.
   * @param ResolveContext $context
   *   The resolve context.
   *
   * @return mixed
   *   The filtered result.
   */
  private static function filterAccessible($value, ResolveContext $context) {
    if ($value instanceof AccessibleInterface) {
      $result = $value->access('view', NULL, TRUE);
      $context->addCacheableDependency($result);
      if ($result->isAllowed()) {
        return $value;
      }

      return NULL;
    }

    // Check arrays of "Accessible" objects.
    if (is_array($value)) {
      $checked = [];
      foreach ($value as $key => $item) {
        // Prevent infinite recursion.
        if ($item instanceof AccessibleInterface) {
          $checked[$key] = self::filterAccessible($item, $context);
        }
        else {
          $checked[$key] = $item;
        }
      }
      return $checked;
    }

    return $value;
  }

  /**
   * Convert the GraphQL field name to the Drupal field name.
   *
   * @param string $fieldName
   *   The GraphQL field name.
   * @param string|null $description
   *   The GraphQL field description.
   *
   * @return string
   *   The Drupal field name.
   */
  private static function getDrupalFieldName(string $fieldName, string|null $description = NULL): string {
    if ($description) {
      // If the description contains e.g. {field: field_foobar_1_a}, use this
      // as the field name.
      $matches = [];
      preg_match('/\{field: (.+)\}/', $description, $matches);
      $match = $matches[1] ?? NULL;
      if ($match) {
        return $match;
      }
    }

    // Convert the field name to snake case.
    return EntitySchemaHelper::toSnakeCase($fieldName);
  }

  /**
   * Resolve a value field.
   *
   * Unlike the FieldItemList fields, these directly resolve to a scalar or
   * other "sane" object type.
   */
  private static function resolveFieldValue($parent, string $fieldName, array $args): array {
    $result = [];

    if ($parent instanceof FieldableEntityInterface) {
      $field = $parent->get($fieldName);

      // Perform access check here because we directly resolve a field value.
      if (!$field->access('view')) {
        return [];
      }

      // Special handling for file fields, which inherit from EntityReferenceItem.
      // Their value fields are not the referenced entity (file), but the field item.
      // This is because some files like images have additional properties like
      // alt and title, which would otherwise not be available on the File
      // type.
      if ($field instanceof FileFieldItemList) {
        return iterator_to_array($field);
      }

      // Entity reference fields, directly get the entities via the
      // referencedEntities helper method.
      if ($field instanceof EntityReferenceFieldItemListInterface) {
        return $field->referencedEntities();
      }

      foreach ($field as $item) {
        $result[] = self::extractFieldValue($item, $args);
      }
    }

    return $result;
  }

  /**
   * Extract the value for a value field.
   *
   * This logic corresponds to the logic in
   * EntitySchemaBuilder::buildGraphqlValueField, where the GraphQL scalar type
   * is determined.
   */
  private static function extractFieldValue(FieldItemInterface $item, array $args) {
    if (
      $item instanceof StringItem ||
      $item instanceof StringItemBase ||
      $item instanceof EmailItem ||
      $item instanceof BooleanItem ||
      $item instanceof ListStringItem ||
      $item instanceof NumericItemBase
    ) {
      return $item->value;
    }
    elseif ($item instanceof TextItemBase) {
      if (isset($args['summary'])) {
        return $item->summary_processed;
      }
      return $item->processed;
    }
    elseif ($item instanceof TimestampItem) {
      $value = $item->value;
      if ($value) {
        return date(\DateTime::ATOM, $value);
      }
    }
    elseif ($item instanceof LanguageItem) {
      return $item->language;
    }
    elseif ($item instanceof FieldItemBase) {
      $pluginId = $item->getPluginId();
      if ($pluginId === 'field_item:telephone') {
        return $item->value;
      }
    }

    return $item;
  }

  /**
   * Resolve item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The graphql context.
   * @param string $property
   *   The proery name.
   *
   * @return \Drupal\Component\Render\MarkupInterface|\Drupal\Core\Entity\ContentEntityInterface|mixed|string|null
   *   The resolved item.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \GraphQL\Error\Error
   */
  private static function resolveItem(FieldItemInterface $item, ResolveInfo $info, string $property) {
    $result = $item->get($property);

    if ($result instanceof Uri) {
      return Url::fromUri($result->getValue());
    }
    elseif ($result instanceof StringData) {
      return $result->getValue() ?? '';
    }
    elseif ($result instanceof BooleanData) {
      return $result->getValue() ?? FALSE;
    }
    elseif ($result instanceof Timestamp) {
      return $result->getValue();
    }
    elseif ($result instanceof IntegerData) {
      return $result->getValue();
    }
    elseif ($result instanceof TextProcessed) {
      return $result->getValue();
    }
    elseif ($result instanceof TypedDataInterface) {
      return $result->getValue();
    }

    $type = $info->returnType;
    $type = $type instanceof WrappingType ? $type->getWrappedType(TRUE) : $type;
    if ($type instanceof ScalarType) {
      $result = is_null($result) ? NULL : $type->serialize($result);
    }

    return $result;
  }

  /**
   * Returns NULL as default type.
   *
   * @param mixed $value
   *   The value.
   * @param \Drupal\graphql\GraphQL\Execution\ResolveContext $context
   *   The context.
   * @param \GraphQL\Type\Definition\ResolveInfo $info
   *   The resolver info.
   *
   * @return null
   *   The default type.
   */
  public static function resolveTypeDefault($value, ResolveContext $context, ResolveInfo $info) {
    if ($value instanceof EntityInterface) {
      $type = EntitySchemaHelper::getGraphqlTypeForEntity($value);
      return $type;
    }
    elseif ($value instanceof FieldItemInterface) {
      return EntitySchemaHelper::getTypeForFieldItem($value);
    }
    elseif ($value instanceof PluginDefinitionInterface) {
      return EntitySchemaHelper::toPascalCase([$value->id(), '_plugin']);
    }
    elseif ($value instanceof Url) {
      return 'DefaultUrl';
    }
    return NULL;
  }

  /**
   * Register field item and field item list resolvers.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  public static function registerFieldListResolvers(ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver(
      'FieldItemList',
      'first',
      self::resolveCallMethod($builder, 'first')
    );
    $registry->addFieldResolver(
      'FieldItemList',
      'isEmpty',
      self::resolveCallMethod($builder, 'isEmpty')
    );
    $registry->addFieldResolver(
      'FieldItemList',
      'count',
      self::resolveCallMethod($builder, 'count')
    );
    $registry->addFieldResolver(
      'FieldItemList',
      'getString',
      self::resolveCallMethod($builder, 'getString')
    );
    $registry->addFieldResolver(
      'FieldItemType',
      'isEmpty',
      self::resolveCallMethod($builder, 'isEmpty')
    );
    $registry->addFieldResolver(
      'FieldItemList',
      'entity',
      self::resolveCallMethod($builder, 'getEntity')
    );
    $registry->addFieldResolver(
      'FieldItemList',
      'list',
      $builder->fromParent()
    );
  }

  /**
   * Register entity resolvers.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  public static function registerEntityResolvers(ResolverRegistry $registry, ResolverBuilder $builder) {
    $resolveEnumArgument = function ($name) {
      return function ($value, $args) use ($name) {
        return str_replace('_', '-', strtolower($args[$name]));
      };
    };

    $registry->addFieldResolver(
      'Entity',
      'id',
      $builder->produce('entity_id')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'label',
      $builder->produce('entity_label')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'uuid',
      $builder->produce('entity_uuid')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'entityTypeId',
      $builder->produce('entity_type_id')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'language',
      $builder->produce('entity_language')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'langcode',
      $builder->callback(function (EntityInterface $entity) {
        return $entity->language()->getId();
      })
    );
    $registry->addFieldResolver(
      'Entity',
      'isNew',
      self::resolveCallMethod($builder, 'isNew')
    );
    $registry->addFieldResolver(
      'Entity',
      'toArray',
      self::resolveCallMethod($builder, 'toArray')
    );
    $registry->addFieldResolver(
      'Entity',
      'uriRelationships',
      self::resolveCallMethod($builder, 'uriRelationships')
    );

    $registry->addFieldResolver(
      'Entity',
      'entityBundle',
      $builder->produce('entity_bundle')->map('entity', $builder->fromParent()),
    );

    $registry->addFieldResolver(
      'Entity',
      'referencedEntities',
      $builder->produce('entity_referenced_entities')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Entity',
      'getConfigTarget',
      self::resolveCallMethod($builder, 'getConfigTarget')
    );
    $registry->addFieldResolver(
      'EntityLinkable',
      'url',
      $builder->produce('entity_url')
        ->map('entity', $builder->fromParent())
        ->map('rel', $builder->fromArgument('rel'))
        ->map('options',
          $builder->produce('url_options')->map('options', $builder->fromArgument('options'))
        )
    );

    $registry->addFieldResolver(
      'Entity',
      'accessCheck',
      $builder->produce('entity_access')
        ->map('entity', $builder->fromParent())
        ->map('operation', $builder->fromArgument('operation'))
    );

    $registry->addFieldResolver(
      'EntityTranslatable',
      'translations',
      $builder->produce('entity_translations')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'EntityTranslatable',
      'translation',
      $builder->produce('entity_translation_fallback')
        ->map('entity', $builder->fromParent())
        ->map('langcode', $builder->callback($resolveEnumArgument('langcode')))
        ->map('fallback', $builder->fromArgument('fallback'))
    );

    $registry->addFieldResolver(
      'EntityDescribable',
      'entityDescription',
      $builder->produce('entity_description')->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'EntityRevisionable',
      'entityRevisionId',
      self::resolveCallMethod($builder, 'getRevisionId')
    );

    $registry->addFieldResolver(
      'EntityRevisionable',
      'wasDefaultRevision',
      self::resolveCallMethod($builder, 'wasDefaultRevision')
    );

    $registry->addFieldResolver(
      'EntityRevisionable',
      'isLatestRevision',
      self::resolveCallMethod($builder, 'isLatestRevision')
    );
  }

  /**
   * Register fields for the base Url fields.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  public static function registerUrlResolvers(ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver(
      'Url',
      'path',
      $builder->produce('url_path')->map('url', $builder->fromParent())
    );
  }

  /**
   * Register language resolvers.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  public static function registerLanguageResolvers(ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver(
      'LanguageInterface',
      'name',
      self::resolveCallMethod($builder, 'getName')
    );
    $registry->addFieldResolver(
      'LanguageInterface',
      'id',
      self::resolveCallMethod($builder, 'getId')
    );
    $registry->addFieldResolver(
      'LanguageInterface',
      'direction',
      self::resolveCallMethod($builder, 'getDirection')
    );
    $registry->addFieldResolver(
      'LanguageInterface',
      'weight',
      self::resolveCallMethod($builder, 'getWeight')
    );
    $registry->addFieldResolver(
      'LanguageInterface',
      'isLocked',
      self::resolveCallMethod($builder, 'isLocked')
    );

    $registry->addTypeResolver('LanguageInterface', function ($value) {
      if ($value instanceof ConfigurableLanguage) {
        return 'ConfigurableLanguage';
      }
      return 'Language';
    });
  }

  /**
   * Register ping resolvers.
   *
   * These are needed because it's possible to not have a single query or
   * mutation when all extensions are disabled. This way we can make sure that
   * the schema can be generated without an exception.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistry $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  public static function registerPingResolvers(ResolverRegistry $registry, ResolverBuilder $builder) {
    $registry->addFieldResolver('Query', 'ping', $builder->fromValue('pong'));
    $registry->addFieldResolver('Mutation', 'ping', $builder->fromValue('pong'));
  }

  /**
   * Return a resolver that calls the method on the parent object.
   *
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   * @param string $method
   *   The method to call.
   *
   * @return DataProducerProxy
   *   The resolver.
   */
  public static function resolveCallMethod(ResolverBuilder $builder, string $method): DataProducerProxy {
    return $builder
      ->produce('call_method')
      ->map('object', $builder->fromParent())
      ->map('method', $builder->fromValue($method));
  }

}
