<?php

namespace Drupal\ai_interpolator\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an OpenAI Interpolator Field rule plugin manager.
 *
 * @see \Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule
 * @see \Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface
 * @see plugin_api
 */
class AiInterpolatorFieldRuleManager extends DefaultPluginManager {

  /**
   * Constructs a AiInterpolatorFieldRule object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AiInterpolatorFieldRules',
      $namespaces,
      $module_handler,
      'Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface',
      'Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule'
    );
    $this->alterInfo('ai_interpolator_field_rule');
    $this->setCacheBackend($cache_backend, 'ai_interpolator_field_rule_plugins');
  }

}
