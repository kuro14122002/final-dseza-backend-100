<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate;

use Drupal\auto_node_translate\Annotation\AutoNodeTranslateProvider;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * AutoNodeTranslateProvider plugin manager.
 */
final class AutoNodeTranslateProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AutoNodeTranslateProvider', $namespaces, $module_handler, AutoNodeTranslateProviderInterface::class, AutoNodeTranslateProvider::class);
    $this->alterInfo('auto_node_translate_provider_info');
    $this->setCacheBackend($cache_backend, 'auto_node_translate_provider_plugins');
  }

}
