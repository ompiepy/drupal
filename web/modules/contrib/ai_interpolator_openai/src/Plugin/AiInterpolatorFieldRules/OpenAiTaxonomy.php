<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\ai_interpolator_openai\OpenAiRequester;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for a taxonomy field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_taxonomy",
 *   title = @Translation("OpenAI Taxonomy"),
 *   field_rule = "entity_reference",
 *   target = "taxonomy_term"
 * )
 */
class OpenAiTaxonomy extends OpenAiBase implements AiInterpolatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Taxonomy';

  /**
   * The OpenAI requester.
   */
  public OpenAiRequester $openAi;

  /**
   * Entity type manager.
   */
  public EntityTypeManagerInterface $entityType;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * Construct a boolean field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\ai_interpolator_openai\OpenAiRequester $openAi
   *   The OpenAI requester.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityType
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, OpenAiRequester $openAi, EntityTypeManagerInterface $entityType, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $openAi);
    $this->openAi = $openAi;
    $this->entityType = $entityType;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_interpolator_openai.request'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
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
    parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    $images = $interpolatorConfig['openai_vision_images'] ? $this->getVisionImages($interpolatorConfig['openai_vision_images'], $entity) : [];
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);

    $total = [];
    // Add to get functional output.
    foreach ($prompts as $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}]";
      try {
        $values = $this->openAi->generateResponse($prompt, $fieldDefinition, $interpolatorConfig, $images);
        // Clean value.
        if ($interpolatorConfig['clean_up']) {
          $values = $this->cleanUpValues($values, $interpolatorConfig['clean_up']);
        }
        // Check for similar.
        if ($interpolatorConfig['search_similar_tags']) {
          $values = $this->searchSimilarTags($values, $entity, $fieldDefinition);
        }
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
   */
  public function searchSimilarTags(array $values, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $list = $this->getTaxonomyList($entity, $fieldDefinition);

    $prompt = "Based on the list of available categories and the list of new categories, could you see somewhere where a new category is not the exact same word, but is contextually similar enough to an old one. For instance \"AMG E55\" would connect to \"Mercedes AMG E55\".\n";
    $prompt .= "If they are the same, you do not need to point them out. In those cases point it out. Only find one suggestion per new category. Be very careful and don't make to crude assumptions.\n\n";
    $prompt .= "Do not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"available_category\": \"The available category\", \"new_category\": \"The new category\"}]\n\n";
    $prompt .= "List of available categories:\n" . implode("\n", $list) . "\n\n";
    $prompt .= "List of new categories:\n" . implode("\n", $values) . "\n\n";
    // GPT-3.5 is not good enough.
    $response = $this->openAi->openAiChatRequest($prompt, ['model' => 'gpt-4']);

    // If there is a response, we use it.
    if (isset($response['choices'][0]['message']['content'])) {
      $data = json_decode($response['choices'][0]['message']['content'], TRUE);
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
    $storage = $this->entityType->getStorage('taxonomy_term');
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
    $storage = $this->entityType->getStorage('taxonomy_term');
    $bundle = !empty($settings['handler_settings']['auto_create_bundle']) ? $settings['handler_settings']['auto_create_bundle'] : key($settings['handler_settings']['target_bundles']);

    $term = $storage->create([
      'vid' => $bundle,
      'name' => $name,
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $term->save();
    return $term;
  }

}
