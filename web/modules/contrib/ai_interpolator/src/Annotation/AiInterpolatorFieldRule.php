<?php

namespace Drupal\ai_interpolator\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Declare a OpenAI Interpolator field rule for a field type.
 *
 * Comes with the simplest solution to inherit for functions.
 *
 * @ingroup ai_interpolator_field_rule
 *
 * @Annotation
 */
class AiInterpolatorFieldRule extends Plugin {

  // All should be translatable.
  use StringTranslationTrait;

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable title of the plugin.
   *
   * @var Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The field rule id.
   */
  public string $field_rule;

  /**
   * The storage target if set.
   */
  public string $target;

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'text_long',
      'text',
      'string',
      'string_long',
      'text_with_summary',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    return [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $delta = 0) {
    $values = $entity->get($interpolatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
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
    return $prompts;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * Helper function if the interpolator needs to load another set of fields.
   *
   * @param Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity type to list on.
   * @param string $type
   *   The field type to get.
   * @param string $target
   *   The target type to get.
   *
   * @return array
   *   The fields found.
   */
  public function getFieldsOfType(ContentEntityInterface $entity, $type, $target = NULL) {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle()); /* @phpstan-ignore-line */
    $names = [];
    foreach ($fields as $fieldDefinition) {
      $fieldTarget = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'] ?? NULL;
      if ($type == $fieldDefinition->getType() && (
        !$target || !$fieldTarget || $fieldTarget == $target)) {
        $names[$fieldDefinition->getName()] = $fieldDefinition->getLabel();
      }
    }
    return $names;
  }

  /**
   * Helper function to enable/disable form field tokens from the entity.
   *
   * @param array $form
   *   The form element, passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function addTokenConfigurationToggle(array &$form, $entity, $fieldDefinition) {
    $form['interpolation_token_configuration_toggle'] = [
      '#type' => 'checkbox',
      '#title' => 'Dynamic Configuration',
      '#description' => $this->t('If you want to set configuration values based on the entity, this will expose token fields for this.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolation_token_configuration_toggle', FALSE),
    ];
  }

  /**
   * Helper function to offer a form field as tokens from the entity.
   *
   * @param string $id
   *   The id.
   * @param array $form
   *   The form element, passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param string $wrapper
   *   If its under a wrapper.
   * @param int $weight
   *   Any added weight.
   */
  public function addTokenConfigurationFormField($id, array &$form, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, $wrapper = "", $weight = 0) {
    $title = $form[$id]['#title'] ?? $id;
    if ($wrapper) {
      $title = $form[$wrapper][$id]['#title'];
    }

    $mergeForm["{$id}_override"] = [
      '#type' => 'details',
      '#title' => $this->t(':word Token', [
        ':word' => $title,
      ]),
      '#states' => [
        'visible' => [
          'input[name="interpolation_token_configuration_toggle"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    if ($weight) {
      $mergeForm["{$id}_override"]['#weight'] = $weight;
    }

    $mergeForm["{$id}_override"]["{$id}_token"] = [
      '#type' => 'textfield',
      '#title' => $this->t(':word Token', [
        ':word' => $title,
      ]),
      '#description' => $this->t('If you want to set this value based on a token, this will overwriten the set value if it exists.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "{$id}_token", ''),
    ];

    // @phpstan-ignore-next-line
    if (\Drupal::service('module_handler')->moduleExists('token')) {
      // @phpstan-ignore-next-line
      $mergeForm["{$id}_override"]['token_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
        \Drupal::service('ai_interpolator.field_config')->getEntityTokenType($entity->getEntityTypeId()), /* @phpstan-ignore-line */
        'current-user',
      ]);
    }

    if ($wrapper) {
      $newForm[$wrapper] = $mergeForm;
    }
    else {
      $newForm = $mergeForm;
    }

    $form = array_merge_recursive($form, $newForm);
  }

  /**
   * Get override value.
   *
   * @param string $id
   *   Key to get value from.
   * @param array $interpolatorConfig
   *   The config.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param mixed $default
   *   A default value if nothing is found.
   *
   * @return mixed
   *   The value.
   */
  public function getConfigValue($id, $interpolatorConfig, $entity, $default = NULL) {
    $configValue = $interpolatorConfig[$id] ?? $default;
    // Return if there is no override.
    if (empty($interpolatorConfig["{$id}_override"])) {
      return $configValue;
    }
    // @phpstan-ignore-next-line
    $entityValue = \Drupal::token()->replace($interpolatorConfig["{$id}_override"], [
      // @phpstan-ignore-next-line
      \Drupal::service('ai_interpolator.field_config')->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => \Drupal::currentUser(), /* @phpstan-ignore-line */
    ]);
    return $entityValue ?? $configValue;
  }

}
