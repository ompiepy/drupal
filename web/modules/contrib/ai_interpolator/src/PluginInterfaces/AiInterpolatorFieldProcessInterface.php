<?php

namespace Drupal\ai_interpolator\PluginInterfaces;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for interpolator modifiers.
 */
interface AiInterpolatorFieldProcessInterface {

  /**
   * Loads a Archive entity by its uuid.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition interface.
   * @param array $interpolatorConfig
   *   The OpenAI Interpolator settings for the field.
   *
   * @return bool
   *   Success or not.
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig);

  /**
   * Preprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   */
  public function preProcessing(EntityInterface $entity);

  /**
   * Postprocessing to set the batch job before each field is run.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   */
  public function postProcessing(EntityInterface $entity);

}
