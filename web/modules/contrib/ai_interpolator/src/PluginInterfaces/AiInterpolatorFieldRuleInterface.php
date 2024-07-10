<?php

namespace Drupal\ai_interpolator\PluginInterfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for field rule modifiers.
 */
interface AiInterpolatorFieldRuleInterface {

  /**
   * Does it need a prompt.
   *
   * @return bool
   *   If it needs a prompt or not.
   */
  public function needsPrompt();

  /**
   * Advanced mode.
   *
   * @return bool
   *   If tokens are available or not.
   */
  public function advancedMode();

  /**
   * Help text.
   *
   * @return string
   *   Help text to show.
   */
  public function helpText();

  /**
   * Allowed inputs.
   *
   * @return array
   *   The array of field inputs to allow.
   */
  public function allowedInputs();

  /**
   * Returns the text that will be placed as placeholder in the textare.
   *
   * @return string
   *   The text.
   */
  public function placeholderText();

  /**
   * Return the Tokens.
   *
   * @return array
   *   Token with replacement as key and description as value.
   */
  public function tokens();

  /**
   * Adds extra form fields to configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return array
   *   Form array with key starting with interpolator_{type}.
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

  /**
   * Adds extra advanced form fields to configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return array
   *   Form array with key starting with interpolator_{type}.
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

  /**
   * Checks if the value is empty on complex field types.
   *
   * @param array $value
   *   The value reponse.
   *
   * @return mixed
   *   Return empty array if empty.
   */
  public function checkIfEmpty(array $value);

  /**
   * Check if the rule is allowed based on config.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return bool
   *   If its allowed or not.
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

  /**
   * Generate the Tokens.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The interpolator config.
   * @param int $delta
   *   The delta in the values.
   *
   * @return array
   *   Token key and token value.
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta);

  /**
   * Generates a response.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The interpolator config.
   *
   * @return array
   *   An array of values.
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig);

  /**
   * Verifies a value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param mixed $value
   *   The value returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return bool
   *   True if verified, otherwise false.
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition);

  /**
   * Stores one or many values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param array $value
   *   The array of mixed value(s) returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return bool|void
   *   True if verified, otherwise false.
   */
  public function storeValues(ContentEntityInterface $entity, array $value, FieldDefinitionInterface $fieldDefinition);

}
