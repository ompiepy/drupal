<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs json output.
 */
class TextToJsonField extends SimpleTextChat {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to JSON field model.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the actor context, give back a list of all the movies that person has been in, together with year of release.\n\nContext:\n{{ context }}\n\n------------------------------\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"movie_title\": \"title of movie\", \"release_year\": \"year of release\"}]";
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
    } elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($interpolatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $interpolatorConfig, $i);
        $prompts[] = \Drupal::service('ai_interpolator.prompt_helper')->renderPrompt($interpolatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompts[$key] = $prompt;
    }
    $total = [];
    foreach ($prompts as $prompt) {
      $values = $this->generateRawResponse($prompt, $interpolatorConfig, $entity, $fieldDefinition);
      if (!empty($values)) {
        $total[] = $values;
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Check so its valid JSON.
    if (empty($value)) {
      return FALSE;
    }
    $json = json_decode($value, TRUE);
    if (empty($json)) {
      return FALSE;
    }
    return TRUE;
  }

}
