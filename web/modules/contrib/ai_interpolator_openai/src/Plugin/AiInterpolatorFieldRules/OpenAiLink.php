<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a Link field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_link",
 *   title = @Translation("OpenAI Link"),
 *   field_rule = "link"
 * )
 */
class OpenAiLink extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Link';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return all links listed with their link texts if available.\n\nContext:\n{{ raw_context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form['interpolator_link_description'] = [
      '#markup' => '<strong>Notice that link interpolation only works with External links</strong>',
    ];
    return $form;
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
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"uri\": \"The raw url\", \"title\": \"The link text if available\"}}]";
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
    // Has to have a link an be valid.
    if (empty($value['uri']) || !filter_var($value['uri'], FILTER_VALIDATE_URL)) {
      return FALSE;
    }
    // If link text is required it has to be set.
    if (empty($value['title']) && $config['title'] == 2) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    foreach ($values as $key => $value) {
      if ($config['title'] == 0) {
        $value['title'] = '';
      }
      $values[$key] = $value;
    }
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
