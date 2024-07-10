<?php

namespace Drupal\ai_interpolator\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Should a field be processed, this can be overwritten with this event.
 */
class ProcessFieldEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_interpolator.process_field';

  // Force the field to be processed.
  const FIELD_FORCE_PROCESS = 'force_process';

  // Force the field to be skipped.
  const FIELD_FORCE_SKIP = 'force_skip';

  // Neutral, let the system decide.
  const FIELD_NEUTRAL = 'neutral';

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  public $entity;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  public $fieldDefinition;

  /**
   * The configuration for the interpolator.
   *
   * @var array
   */
  public $interpolatorConfig;

  /**
   * The changes made.
   */
  public $actions = [];

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $interpolatorConfig
   *   The configuration for the interpolator.
   */
  public function __construct(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
    $this->interpolatorConfig = $interpolatorConfig;
  }

  /**
   * Force the field to be processed.
   */
  public function setForceProcess() {
    $this->actions[] = self::FIELD_FORCE_PROCESS;
  }

  /**
   * Force the field to be skipped.
   */
  public function setForceSkip() {
    $this->actions[] = self::FIELD_FORCE_SKIP;
  }

  /**
   * Neutral, let the system decide.
   */
  public function setNeutral() {
    $this->actions[] = self::FIELD_NEUTRAL;
  }
}
