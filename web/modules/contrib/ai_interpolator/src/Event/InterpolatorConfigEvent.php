<?php

namespace Drupal\ai_interpolator\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Changes to the interpolator config can be made here.
 */
class InterpolatorConfigEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_interpolator.interpolator_config';

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The configuration for the interpolator.
   *
   * @var array
   */
  protected $interpolatorConfig;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param array $interpolatorConfig
   *   The configuration for the interpolator.
   */
  public function __construct(ContentEntityInterface $entity, array $interpolatorConfig) {
    $this->entity = $entity;
    $this->interpolatorConfig = $interpolatorConfig;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the interpolator config.
   *
   * @return array
   *   The interpolator config.
   */
  public function getInterpolatorConfig() {
    return $this->interpolatorConfig;
  }

  /**
   * Set the interpolator config.
   *
   * @param array $interpolatorConfig
   *   The interpolator config.
   */
  public function setInterpolatorConfig(array $interpolatorConfig) {
    $this->interpolatorConfig = $interpolatorConfig;
  }

}
