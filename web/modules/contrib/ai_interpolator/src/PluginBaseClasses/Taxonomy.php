<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs taxonomy rules.
 */
class Taxonomy extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This helps to choose or create categories.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text choose up to {{ max_amount }} categories from the category context that fits the text.\n\nCategory options:\n{{ value_options_comma }}\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    $tokens = parent::tokens();
    $tokens['value_options_comma'] = 'A comma separated list of all value options.';
    $tokens['value_options_nl'] = 'A new line separated list of all value options.';
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $interpolatorConfig, $delta);
    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    $values = array_values($list);

    $tokens['value_options_comma'] = implode(', ', $values);
    $tokens['value_options_nl'] = implode("\n", $values);
    return $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition);
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    $form['interpolator_clean_up'] = [
      '#type' => 'select',
      '#title' => 'Text Manipulation',
      '#description' => $this->t('These are possible text manipulations to run on each created tag.'),
      '#options' => [
        '' => $this->t('None'),
        'lowercase' => $this->t('lowercase'),
        'uppercase' => $this->t('UPPERCASE'),
        'first_char' => $this->t('First character uppercase'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_clean_up', ''),
      '#weight' => 23,
    ];

    if ($settings['handler_settings']['auto_create']) {
      $form['interpolator_search_similar_tags'] = [
        '#type' => 'checkbox',
        '#title' => 'Find similar tags',
        '#description' => $this->t('This will use GPT-4 to find similar tags. Meaning if the tag "Jesus Christ" exists and the system wants to store "Jesus" it will store it as "Jesus Christ". This uses extra calls and is slower and more costly.'),
        '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_search_similar_tags', FALSE),
        '#weight' => 23,
      ];
    }

    return $form;
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
        // Clean value.
        if ($interpolatorConfig['clean_up']) {
          $values = $this->cleanUpValues($values, $interpolatorConfig['clean_up']);
        }
        // Check for similar.
        if ($interpolatorConfig['search_similar_tags']) {
          $values = $this->searchSimilarTags($values, $entity, $fieldDefinition, $interpolatorConfig);
        }
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    // If it's auto create and its a text field, create.
    if ($settings['handler_settings']['auto_create'] && is_string($value)) {
      return TRUE;
    }

    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    $values = array_values($list);

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
    $settings = $fieldDefinition->getConfig($entity->bundle())->getSettings();

    $list = $this->getTaxonomyList($entity, $fieldDefinition);
    // If it's not in the keys, go through values.
    $newValues = [];
    foreach ($values as $key => $value) {
      foreach ($list as $tid => $name) {
        if ($value == $name) {
          $newValues[$key] = $tid;
        }
      }

      // If auto create, we create new ones.
      if (!isset($newValues[$key]) && $settings['handler_settings']['auto_create']) {
        $term = $this->generateTag($value, $settings);
        $newValues[$key] = $term->id();
      }
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $newValues);
    return TRUE;
  }

  /**
   * Looks for similar tags using GPT-3.5.
   *
   * @param array $values
   *   The values to search for.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The configuration.
   */
  public function searchSimilarTags(array $values, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, $interpolatorConfig) {
    $list = $this->getTaxonomyList($entity, $fieldDefinition);

    $prompt = "Based on the list of available categories and the list of new categories, could you see somewhere where a new category is not the exact same word, but is contextually similar enough to an old one. For instance \"AMG E55\" would connect to \"Mercedes AMG E55\".\n";
    $prompt .= "If they are the same, you do not need to point them out. In those cases point it out. Only find one suggestion per new category. Be very careful and don't make to crude assumptions.\n\n";
    $prompt .= "Do not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"available_category\": \"The available category\", \"new_category\": \"The new category\"}]\n\n";
    $prompt .= "List of available categories:\n" . implode("\n", $list) . "\n\n";
    $prompt .= "List of new categories:\n" . implode("\n", $values) . "\n\n";
    $data = $this->generateRawResponse($prompt, $interpolatorConfig, $entity, $fieldDefinition);
    // If there is a response, we use it.
    if (!empty($data)) {
      foreach ($data as $change) {
        // If it's not the same, we change it.
        if (!empty($change['available_category']) && !empty($change['new_category']) && $change['new_category'] != $change['available_category']) {
          foreach ($values as $key => $val) {
            if ($val == $change['new_category']) {
              $values[$key] = $change['available_category'];
            }
          }
        }
      }
      // Do a last sweep so we don't have doublets.
      $values = array_unique($values);
    }

    return $values;
  }

  /**
   * Helper function to clean up values.
   *
   * @param array $values
   *   The values to clean up.
   * @param string $cleanUp
   *   The clean up type.
   *
   * @return array
   *   The cleaned up values.
   */
  public function cleanUpValues(array $values, $cleanUp) {
    $newValues = [];
    foreach ($values as $key => $value) {
      if ($cleanUp == 'lowercase') {
        $newValues[$key] = strtolower($value);
      }
      elseif ($cleanUp == 'uppercase') {
        $newValues[$key] = strtoupper($value);
      }
      elseif ($cleanUp == 'first_char') {
        $newValues[$key] = ucfirst($value);
      }
    }
    return $newValues;
  }

  /**
   * Helper function to get possible values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return array
   *   Array of tid as key and name as value.
   */
  protected function getTaxonomyList(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    /** @var \Drupal\taxonomy\TermStorage */
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $returnTerms = [];
    // Get vocabularies and get taxonomies from that.
    foreach ($config['handler_settings']['target_bundles'] as $vid) {
      $terms = $storage->loadTree($vid);
      foreach ($terms as $term) {
        $returnTerms[$term->tid] = $term->name;
      }
    }
    return $returnTerms;
  }

  /**
   * Helper function to generate new tags.
   *
   * @param string $name
   *   The name of the taxonomy.
   * @param array $settings
   *   The field config settings.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   A taxonomy term.
   */
  protected function generateTag($name, array $settings) {
    /** @var \Drupal\taxonomy\TermStorage */
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $bundle = !empty($settings['handler_settings']['auto_create_bundle']) ? $settings['handler_settings']['auto_create_bundle'] : key($settings['handler_settings']['target_bundles']);

    $term = $storage->create([
      'vid' => $bundle,
      'name' => $name,
      'status' => 1,
      'uid' => \Drupal::currentUser()->id(),
    ]);
    $term->save();
    return $term;
  }

}
