<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for an integer field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_integer",
 *   title = @Translation("OpenAI Integer"),
 *   field_rule = "integer"
 * )
 */
class OpenAiInteger extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Integer';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text add a sentiment rating between {{ min }} and {{ max }}, where {{ min }} means really negative sentiment and {{ max }} means really great sentiment. Answer with a full number.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['min'] = 'A min numeric value, if set.';
    $tokens['max'] = 'A max numeric value, if set.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $tokens = parent::generateTokens($entity, $fieldDefinition, $interpolatorConfig, $delta);
    $tokens['min'] = $config['min'] ?? NULL;
    $tokens['max'] = $config['max'] ?? NULL;
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
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]";
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
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // Has to be number.
    if (!is_numeric($value)) {
      return FALSE;
    }
    // Has to be larger or equal to min.
    if (is_numeric($config['min']) && $config['min'] >= $value) {
      return FALSE;
    }
    // Has to be smaller or equal to max.
    if (is_numeric($config['max']) && $config['max'] <= $value) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Since we allow any type of number we round it.
    $values = array_map(fn($value) => round($value, 0), $values);
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
