<?php

namespace Drupal\jsonapi_image_styles\Plugin\Field\FieldType;

use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

/**
 * Represents the computed image styles for a file entity.
 */
class ImageStyleNormalizedFieldItemList extends MapFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $config = \Drupal::config('jsonapi_image_styles.settings');
    $styles = [];
    $entity = $this->getEntity();
    $uri = ($entity instanceof File && substr($entity->getMimeType(), 0, 5) === 'image') ? $entity->getFileUri() : FALSE;

    if ($uri) {
      $defined_styles = $config->get('image_styles') ?? [];
      if (!empty(array_filter($defined_styles))) {
        foreach ($defined_styles as $key) {
          $styles[$key] = ImageStyle::load($key);
        }
      }
      else {
        $styles = ImageStyle::loadMultiple();
      }

      $uris = [];
      foreach ($styles as $name => $style) {
        if ($style instanceof ImageStyle) {
          $uris[$name] = $style->buildUrl($uri);
        }
      }
      $this->list[] = $this->createItem(0, $uris);
    }
  }

}
