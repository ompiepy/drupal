<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a office_hours field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_office_hours",
 *   title = @Translation("OpenAI Office Hours"),
 *   field_rule = "office_hours"
 * )
 */
class OpenAiOfficeHours extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Office Hours';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return the office hours that you can find.\n\nContext:\n{{ context }}";
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
      $prompt .= "\n\n\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"day\": \"1 for monday, 2 for tuesday and so on\", \"starthours\": \"opening hour in hi format, so 16:00 would be 1600\", \"endhours\": \"closing hour in hi format, so 20:00 would be 2000\"}].\n\nOnly give back the days they are open.";
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
    // Has to be valid day.
    if (!empty($value['day']) && !empty($value['starthours']) && !empty($value['endhours'])) {
      return TRUE;
    }
    // Otherwise it is not ok.
    return FALSE;
  }

}
