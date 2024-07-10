<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a Telephone field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_telephone",
 *   title = @Translation("OpenAI Telephone"),
 *   field_rule = "telephone"
 * )
 */
class OpenAiTelephone extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Telephone';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all telephone numbers listed with a country code added.\n\nContext:\n{{ context }}";
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
      $prompt .= "\n\n\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"plain telephone number with plus and country code\"}]";
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
    // Has to be valid telephone number.
    $pattern = '/^\+\(?[0-9]{1,3}\)?[ ]?[0-9]{6,12}$/';
    if (preg_match($pattern, $value)) {
      return TRUE;
    }
    // Otherwise it is not ok.
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
