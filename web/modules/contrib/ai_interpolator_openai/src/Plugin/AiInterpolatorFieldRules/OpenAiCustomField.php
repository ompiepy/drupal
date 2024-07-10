<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for the custom field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_custom_field",
 *   title = @Translation("OpenAI Custom Field"),
 *   field_rule = "custom"
 * )
 */
class OpenAiCustomField extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Custom Field';

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context extract all quotes and fill in the quote, a translated quote into english, the persons name and the persons role.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return isset($value[0]) && $value[0][key($value[0])] ? [1] : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition);

    if (isset($config['field_settings'])) {
      foreach ($config['field_settings'] as $key => $value) {
        $form['interpolator_openai_custom_value_' . $key] = [
          '#type' => 'textfield',
          '#title' => $value['widget_settings']['label'],
          '#description' => $this->t('One sentence how the %label should be filled out. For instance "the original quote".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_custom_value_' . $key, ''),
          '#weight' => 14,
        ];

        $form['interpolator_openai_custom_oneshot_' . $key] = [
          '#type' => 'textfield',
          '#title' => $this->t('Example %label', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#description' => $this->t('One example %label of a filled out value for one shot learning. For instance "To be or not to be".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_custom_oneshot_' . $key, ''),
          '#weight' => 14,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    $images = $interpolatorConfig['openai_vision_images'] ? $this->getVisionImages($interpolatorConfig['openai_vision_images'], $entity) : [];
    $oneShot = [];
    $example = [];
    foreach ($interpolatorConfig as $key => $value) {
      if (substr($key, 0, 20) == 'openai_custom_value_') {
        $example[substr($key, 20)] = $value;
      }
      elseif (substr($key, 0, 22) == 'openai_custom_oneshot_') {
        $oneShot[substr($key, 22)] = $value;
      }
    }

    $total = [];
    // Add to get functional output.
    foreach ($prompts as $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($example) . "}]";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($oneShot) . "}]\n";
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
    // Should be array, otherwise no validation for now.
    if (!is_array($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
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
