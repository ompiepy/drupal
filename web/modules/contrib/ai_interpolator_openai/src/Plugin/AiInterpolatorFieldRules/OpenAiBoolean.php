<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for an boolean field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_boolean",
 *   title = @Translation("OpenAI Boolean"),
 *   field_rule = "boolean"
 * )
 */
class OpenAiBoolean extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Boolean';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text answer with a {{ true }} if there is some information about Pippi Longstockings. Otherwise answer {{ false }}.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['true'] = 'The true value.';
    $tokens['false'] = 'The false value.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $interpolatorConfig);
    $tokens['true'] = 'TRUE';
    $tokens['false'] = 'FALSE';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    $images = $interpolatorConfig['openai_vision_images'] ? $this->getVisionImages($interpolatorConfig['openai_vision_images'], $entity) : [];

    $total = [];
    // Add to get functional output.
    foreach ($prompts as $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"TRUE or FALSE\"}]";
      try {
        $values = $this->openAi->generateResponse($prompt, $fieldDefinition, $interpolatorConfig, $images);
        $total = array_merge_recursive($total, $values);
      }
      catch (\Exception $e) {

      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Has to be string boolean.
    if (!in_array($value, ['TRUE', 'FALSE', '0', '1'])) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Transform string to boolean.
    foreach ($values as $key => $value) {
      $values[$key] = in_array($value, ['TRUE', '1']) ? TRUE : FALSE;
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
