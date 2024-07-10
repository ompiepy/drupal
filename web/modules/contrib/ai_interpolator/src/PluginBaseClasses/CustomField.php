<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple custom field rules.
 */
class CustomField extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can help find complex amount of data and fill in complex field types with it.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context extract all quotes and fill in the quote, a translated quote into english, the persons name and the persons role.\n\nContext:\n{{ context }}";
  }

  /**
   * Adds the custom form fields.
   *
   * @param string $prefix
   *   The prefix.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return array
   *   The form.
   */
  public function addCustomFormFields($prefix, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    if (isset($config['field_settings'])) {
      foreach ($config['field_settings'] as $key => $value) {
        $form["interpolator_{$prefix}_custom_value_" . $key] = [
          '#type' => 'textfield',
          '#title' => $value['widget_settings']['label'],
          '#description' => $this->t('One sentence how the %label should be filled out. For instance "the original quote".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_custom_value_" . $key, ''),
          '#weight' => 14,
        ];

        $form["interpolator_{$prefix}_custom_oneshot_" . $key] = [
          '#type' => 'textfield',
          '#title' => $this->t('Example %label', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#description' => $this->t('One example %label of a filled out value for one shot learning. For instance "To be or not to be".', [
            '%label' => $value['widget_settings']['label'],
          ]),
          '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_custom_oneshot_" . $key, ''),
          '#weight' => 14,
        ];
      }
    }

    return $form;
  }

  /**
   * Generate the prompts needed for a custom field.
   *
   * @param string $prefix
   *   The prefix.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $interpolatorConfig
   *   The config.
   *
   * @return array
   *   The prompts.
   */
  public function generatePrompts($prefix, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($interpolatorConfig['mode']) && $interpolatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_interpolator.prompt_helper')->renderTokenPrompt($interpolatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($interpolatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $interpolatorConfig, $i);
        $prompts[] = \Drupal::service('ai_interpolator.prompt_helper')->renderPrompt($interpolatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    foreach ($interpolatorConfig as $key => $value) {
      if (str_starts_with($key, $prefix . '_custom_value_')) {
        $example[substr($key, strlen($prefix . '_custom_value_'))] = $value;
      }
      elseif (str_starts_with($key, $prefix . '_custom_oneshot_')) {
        $oneShot[substr($key, strlen($prefix . '_custom_oneshot_'))] = $value;
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($example) . "}]";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($oneShot) . "}]\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    foreach ($prompts as $prompt) {
      $values = $this->generateResponse($prompt, $interpolatorConfig, $entity, $fieldDefinition);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
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

}
