<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a list string field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_list_string",
 *   title = @Translation("OpenAI List String"),
 *   field_rule = "list_string"
 * )
 */
class OpenAiListString extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI List String';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return $this->t("Choose applicable items from a string list. <br><br>Make sure that you use one of the options as context, otherwise it does not know what too choose.");
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text choose up to {{ max_amount }} categories from the category context that fits the text.\n\nCategory: {{ options_comma }}\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['options_comma'] = 'A comma separated list of all options.';
    $tokens['options_nl'] = 'A new line separated list of all options.';
    $tokens['value_options_comma'] = 'A comma separated list of all value options.';
    $tokens['value_options_nl'] = 'A new line separated list of all value options.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $interpolatorConfig, $delta);
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $keys = array_keys($config['allowed_values']);
    $values = array_values($config['allowed_values']);

    $tokens['options_comma'] = implode(', ', $keys);
    $tokens['options_nl'] = implode("\n", $keys);
    $tokens['value_options_comma'] = implode(', ', $values);
    $tokens['value_options_nl'] = implode("\n", $values);
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
    $keys = array_keys($config['allowed_values']);
    $values = array_values($config['allowed_values']);
    $values = array_merge($keys, $values);

    // Has to be in the list.
    if (!in_array($value, $values)) {
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
    $keys = array_keys($config['allowed_values']);
    $realValues = [];
    // If it's not in the keys, go through values.
    foreach ($values as $value) {
      $realValue = '';
      if (!in_array($value, $keys)) {
        foreach ($config['allowed_values'] as $key => $name) {
          if ($value == $name) {
            $realValue = $key;
          }
        }
      }
      else {
        $realValue = $value;
      }
      $realValues[] = $realValue;
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $realValues);
    return TRUE;
  }

}
