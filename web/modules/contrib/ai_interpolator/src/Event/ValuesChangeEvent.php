<?php

namespace Drupal\ai_interpolator\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Change the values before they are validated and stored.
 */
class ValuesChangeEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_interpolator.change_value';

  /**
   * The values to process.
   *
   * @var array
   */
  protected $values;

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The interpolator config.
   *
   * @var array
   */
  protected $interpolatorConfig;

  /**
   * Constructs the object.
   *
   * @param array $values
   *   The values to process.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $interpolatorConfig
   *   The interpolator config.
   */
  public function __construct(array $values, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $this->values = $values;
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
    $this->interpolatorConfig = $interpolatorConfig;
  }

  /**
   * Get the values.
   *
   * @return array
   *   The values.
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * Get the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  public function getFieldDefinition() {
    return $this->fieldDefinition;
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
   * Set the new values.
   *
   * @param array $values
   *   The new values.
   */
  public function setValues(array $values) {
    $this->values = $values;
  }


}
