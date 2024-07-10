<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a FAQ field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_faq",
 *   title = @Translation("OpenAI FAQ Field"),
 *   field_rule = "faqfield"
 * )
 */
class OpenAiFaqField extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI FAQ Field';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return 5 questions and answers.\n\nContext:\n{{ context }}";
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
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response with questions and answers following this format without deviation.\n[{\"value\": {\"question\": \"The question to ask\", \"answer\": \"The answer\"}}]";
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
    if (empty($value['question']) || empty($value['answer'])) {
      return FALSE;
    }
    return TRUE;
  }

}
