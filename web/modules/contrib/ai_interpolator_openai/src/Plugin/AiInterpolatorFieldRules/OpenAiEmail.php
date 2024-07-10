<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a E-Mail field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_email",
 *   title = @Translation("OpenAI E-Mail"),
 *   field_rule = "email"
 * )
 */
class OpenAiEmail extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI E-Mail';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all e-mails listed.\n\nContext:\n{{ context }}";
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
      $prompt .= "\n\n\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"plain e-mail address\"}]";
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
    // Has to be valid e-mail.
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
      return TRUE;
    }
    // Otherwise it is not ok.
    return FALSE;
  }

}
