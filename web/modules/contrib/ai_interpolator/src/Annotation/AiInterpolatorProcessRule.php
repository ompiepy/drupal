<?php

namespace Drupal\ai_interpolator\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Declare a OpenAI Interpolator process rule for AI.
 *
 * Comes with the simplest solution to inherit for functions.
 *
 * @ingroup ai_interpolator_process_rule
 *
 * @Annotation
 */
class AiInterpolatorProcessRule extends Plugin {

  // All should be translatable.
  use StringTranslationTrait;

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable title of the plugin.
   *
   * @var Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The human-readable description of the plugin.
   *
   * @var Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
