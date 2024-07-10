<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs list rules.
 */
class Options extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This helps to choose from a list of options.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text add a sentiment rating from the sentiment list, where {{ min }} means negative and {{ max }} means positive.\n\nRatings: {{ options_nl }}\n\nContext:\n{{ context }}";
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
    $tokens['min'] = 'A min numeric value, if set.';
    $tokens['max'] = 'A max numeric value, if set.';
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

    $tokens['min'] = min($keys) ?? NULL;
    $tokens['max'] = max($keys) ?? NULL;
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

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]";
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
