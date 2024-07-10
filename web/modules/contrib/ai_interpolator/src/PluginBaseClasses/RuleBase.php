<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class for all rule helpers.
 */
class RuleBase extends AiInterpolatorFieldRule {

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return 'Enter a prompt here.';
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'text_long',
      'text',
      'string',
      'string_long',
      'text_with_summary',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    return [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $values = $entity->get($interpolatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * Gets the general helper.
   *
   * @return \Drupal\ai_interpolator\Rulehelpers\GeneralHelper
   *   The general helper.
   */
  public function getGeneralHelper() {
    return \Drupal::service('ai_interpolator.rule_helper.general');
  }

  /**
   * Mockup for generating response, have to be filled in by the rule.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $interpolatorConfig
   *   The configuration.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return mixed
   *   The response.
   */
  public function generateResponse($prompt, $interpolatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return NULL;
  }

  /**
   * Mockup for generating raw response, have to be filled in by the rule.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $interpolatorConfig
   *   The configuration.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return mixed
   *   The response.
   */
  public function generateRawResponse($prompt, $interpolatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return NULL;
  }

}
