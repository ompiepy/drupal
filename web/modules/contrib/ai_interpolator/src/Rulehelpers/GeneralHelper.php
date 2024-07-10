<?php

namespace Drupal\ai_interpolator\Rulehelpers;

use Drupal\ai_interpolator\FormAlter\AiInterpolatorFieldConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\token\TreeBuilder;

/**
 * Helper functions for most rules.
 */
class GeneralHelper {

  use StringTranslationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The AI Interpolator field config.
   *
   * @var \Drupal\ai_interpolator\FormAlter\AiInterpolatorFieldConfig
   */
  protected $aiInterpolatorFieldConfig;

  /**
   * The token system.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\ai_interpolator\FormAlter\AiInterpolatorFieldConfig $aiInterpolatorFieldConfig
   *   The AI Interpolator field config.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityFieldManagerInterface $entityFieldManager,
    ModuleHandlerInterface $moduleHandler,
    AiInterpolatorFieldConfig $aiInterpolatorFieldConfig,
    Token $token,
    AccountProxyInterface $currentUser
  ) {
    $this->entityFieldManager = $entityFieldManager;
    $this->moduleHandler = $moduleHandler;
    $this->aiInterpolatorFieldConfig = $aiInterpolatorFieldConfig;
    $this->token = $token;
    $this->currentUser = $currentUser;
  }

  /**
   * This takes a possible JSON response from a LLM and cleans it up.
   *
   * @param string $response
   *   The response from the LLM.
   *
   * @return array
   *   The cleaned up JSON response.
   */
  public function parseJson($response) {
    // Look for the json start with [ or { and stop with } or ] using regex.
    if (preg_match('/[\[\{].*[\}\]]/s', $response, $matches)) {
      $response = $matches[0];
    }
    // Try to decode.
    $json = json_decode($response, TRUE);
    // Sometimes it doesn't become a valid JSON response, but many.
    if (!is_array($json)) {
      $newJson = [];
      foreach (explode("\n", $response) as $row) {
        if ($row) {
          $parts = json_decode(str_replace("\n", "", $row), TRUE);
          if (is_array($parts)) {
            $newJson = array_merge($newJson, $parts);
          }
        }
      }
      if (!empty($newJson)) {
        $json = $newJson;
      }
    }
    if (isset($json[0]['value'])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val['value'])) {
          $values[] = $val['value'];
        }
      }
      return $values;
    }
    // Sometimes it sets the wrong key.
    elseif (isset($json[0])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val[key($val)])) {
          $values[] = $val[key($val)];
        }
        return $values;
      }
    }
    // Sometimes it does not return with values in GPT 3.5.
    elseif (is_array($json) && isset($json[0][0])) {
      $values = [];
      foreach ($json as $vals) {
        foreach ($vals as $val) {
          if (isset($val)) {
            $values[] = $val;
          }
        }
      }
      return $values;
    } elseif (isset($json['value'])) {
      return [$json['value']];
    } else {
      return [$response['choices'][0]['message']['content']];
    }
  }

  /**
   * Adds common LLM parameters to the form.
   *
   * @param string $prefix
   *   The prefix for the form.
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function addCommonLlmParametersFormFields($prefix, array &$form, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form["interpolator_{$prefix}_temperature"] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_temperature", 0.5),
      '#description' => $this->t('The temperature of the model, the higher the more creative, the lower the more factual.'),
      '#min' => 0,
      '#max' => 2,
      '#step' => '0.1',
    ];

    $form["interpolator_{$prefix}_max_tokens"] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_max_tokens", 1024),
      '#description' => $this->t('The maximum number of tokens to generate.'),
    ];

    $form["interpolator_{$prefix}_top_p"] = [
      '#type' => 'number',
      '#title' => $this->t('AI Top P'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_top_p", 1),
      '#description' => $this->t('The nucleus sampling probability.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => '0.1',
    ];

    $form["interpolator_{$prefix}_top_k"] = [
      '#type' => 'number',
      '#title' => $this->t('AI Top K'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_top_k", 50),
      '#description' => $this->t('The top k sampling probability.'),
      '#min' => 0,
      '#max' => 100,
    ];

    $form["interpolator_{$prefix}_frequency_penalty"] = [
      '#type' => 'number',
      '#title' => $this->t('Frequency Penalty'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_frequency_penalty", 0),
      '#description' => $this->t('The frequency penalty.'),
      '#min' => -2,
      '#max' => 2,
    ];

    $form["interpolator_{$prefix}_presence_penalty"] = [
      '#type' => 'number',
      '#title' => $this->t('Presence Penalty'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', "interpolator_{$prefix}_presence_penalty", 0),
      '#description' => $this->t('The presence penalty.'),
      '#min' => -2,
      '#max' => 2,
    ];
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
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle()); /* @phpstan-ignore-line */
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
   * Base64 encode an image.
   *
   * @param \Drupal\file\FileInterface $imageEntity
   *   The image entity.
   *
   * @return string
   *   The base64 encoded image.
   */
  public function base64EncodeFileEntity(FileInterface $imageEntity) {
    return 'data:' . $imageEntity->getMimeType() . ';base64,' . base64_encode(file_get_contents($imageEntity->getFileUri()));
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

    if ($this->moduleHandler->moduleExists('token')) {
      // @phpstan-ignore-next-line
      $mergeForm["{$id}_override"]['token_help'] = \Drupal::service('@token.tree_builder')->buildRenderable([
        $this->aiInterpolatorFieldConfig->getEntityTokenType($entity->getEntityTypeId()),
        'current-user',
      ]);
    }

    if ($wrapper) {
      $newForm[$wrapper] = $mergeForm;
    } else {
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
    $entityValue = $this->token->replace($interpolatorConfig["{$id}_override"], [
      $this->aiInterpolatorFieldConfig->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => $this->currentUser,
    ]);
    return $entityValue ?? $configValue;
  }

}
