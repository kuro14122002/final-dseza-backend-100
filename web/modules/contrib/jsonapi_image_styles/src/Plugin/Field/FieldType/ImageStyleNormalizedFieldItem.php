<?php

namespace Drupal\jsonapi_image_styles\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;

/**
 * Plugin implementation of the 'image_style_uri' field type.
 *
 * @FieldType(
 *   id = "image_style_uri",
 *   label = @Translation("Image style uri"),
 *   description = @Translation("Normalized image style paths"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\jsonapi_image_styles\Plugin\Field\FieldType\ImageStyleNormalizedFieldItemList",
 * )
 */
class ImageStyleNormalizedFieldItem extends MapItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

}
