<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs simple text chat/instructions.
 */
class SimpleTextChat extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to text model.";
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
    $total = [];
    foreach ($prompts as $prompt) {
      $value = $this->generateResponse($prompt, $interpolatorConfig, $entity, $fieldDefinition);
      if (!empty($value)) {
        $total = array_merge_recursive($total, $value);
      }
    }
    return $total;
  }

}
