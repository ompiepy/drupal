<?php

namespace Drupal\ai_interpolator\FormAlter;

use Drupal\ai_interpolator\AiFieldRules;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldProcessManager;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;

/**
 * A helper to store configs for fields.
 */
class AiInterpolatorFieldConfig {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The processes available.
   */
  protected AiInterpolatorFieldProcessManager $processes;

  /**
   * Constructs a field config modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_interpolator\AiFieldRules $fieldRules
   *   The field rule manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match interface.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module handler.
   * @param \Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldProcessManager $processes
   *   The process manager.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiFieldRules $fieldRules, RouteMatchInterface $routeMatch, ModuleHandlerInterface $moduleHandler, AiInterpolatorFieldProcessManager $processes) {
    $this->fieldManager = $fieldManager;
    $this->fieldRules = $fieldRules;
    $this->routeMatch = $routeMatch;
    $this->moduleHandler = $moduleHandler;
    $this->processes = $processes;
  }

  /**
   * Alter the form with field config if applicable.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function alterForm(array &$form, FormStateInterface $formState) {
    // Get the entity and the field name.
    $entity = $form['#entity'];

    // Try different ways to get the field name.
    $fieldName = NULL;
    $routeParameters = $this->routeMatch->getParameters()->all();
    if (!empty($routeParameters['field_name'])) {
      $fieldName = $routeParameters['field_name'];
    }
    elseif (!empty($routeParameters['field_config'])) {
      $fieldName = $routeParameters['field_config']->getName();
    }
    elseif (!empty($routeParameters['base_field_override'])) {
      $fieldName = $routeParameters['base_field_override']->getName();
    }

    // If no field name it is not for us.
    if (!$fieldName) {
      return;
    }

    // Get the field config.
    $fields = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    /** @var \Drupal\field\Entity\FieldConfig */
    $fieldInfo = $fields[$fieldName] ?? NULL;

    // Try to get it from the form session if not existing.
    if (!$fieldInfo) {
      $fieldInfo = $formState->getFormObject()->getEntity();
    }

    // The info might not have been saved yet.
    if (!$fieldInfo) {
      return;
    }

    // Find the rules. If not found don't do anything.
    $rules = $this->fieldRules->findRuleCandidates($entity, $fieldInfo);

    if (empty($rules)) {
      return;
    }

    $form['interpolator_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AI Interpolator'),
      '#description' => $this->t('If you want this value to be auto filled from AI'),
      '#weight' => 15,
      '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_enabled', 0),
      '#attributes' => [
        'name' => 'interpolator_enabled',
      ],
    ];

    $rulesOptions = [];
    foreach ($rules as $ruleKey => $rule) {
      $rulesOptions[$ruleKey] = $rule->title;
    }

    $chosenRule = $formState->getValue('interpolator_rule') ?? $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_rule', '');
    $chosenRule = $chosenRule ? $chosenRule : key($rulesOptions);
    $rule = $rules[$chosenRule] ?? $rules[key($rulesOptions)];

    $form['interpolator_rule'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose AI Interpolator Rule'),
      '#description' => $this->t('Some field type might have many rules to use, based on the modules you installed'),
      '#weight' => 16,
      '#options' => $rulesOptions,
      '#default_value' => $chosenRule,
      '#states' => [
        'visible' => [
          'input[name="interpolator_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      // Update dynamically.
      '#ajax' => [
        'callback' => [$this, 'updateRule'],
        'event' => 'change',
        'wrapper' => 'interpolator-container',
      ],
    ];

    // Show help text.
    if ($rule->helpText()) {
      $form['interpolator_help_text'] = [
        '#type' => 'details',
        '#title' => $this->t('About this rule'),
        '#weight' => 17,
        '#states' => [
          'visible' => [
            'input[name="interpolator_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['interpolator_help_text']['help_text'] = [
        '#markup' => $rule->helpText(),
      ];
    }

    $form['interpolator_container'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Interpolator Settings'),
      '#weight' => 18,
      '#open' => TRUE,
      '#attributes' => [
        'id' => [
          'interpolator-container',
        ],
      ],
      '#states' => [
        'visible' => [
          'input[name="interpolator_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['interpolator_container'] = array_merge($form['interpolator_container'], $rule->extraFormFields($entity, $fieldInfo));

    $modeOptions['base'] = $this->t('Base Mode');
    // Not every rule allows advanced mode.
    if ($rule->advancedMode()) {
      $modeOptions['token'] = $this->t('Advanced Mode (Token)');
    }

    if ($this->moduleHandler->moduleExists('token')) {
      $form['interpolator_container']['interpolator_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Interpolator Input Mode'),
        '#description' => $this->t('If you have token installed you can use it in advanced mode, otherwise it uses base mode.'),
        '#options' => $modeOptions,
        '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_mode', 'base'),
        '#weight' => 5,
        '#attributes' => [
          'name' => 'interpolator_mode',
        ],
      ];
    }
    else {
      $form['interpolator_container']['interpolator_mode'] = [
        '#value' => 'base',
      ];
    }

    // Prompt with token.
    $form['interpolator_container']['normal_prompt'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#weight' => 11,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_mode"]' => [
            'value' => 'base',
          ],
        ],
      ],
    ];
    // Create Options for base field.
    $baseFieldOptions = [];
    foreach ($fields as $fieldId => $fieldData) {
      if (in_array($fieldData->getType(), $rule->allowedInputs()) && $fieldId != $fieldName) {
        $baseFieldOptions[$fieldId] = $fieldData->getLabel();
      }
    }

    $form['interpolator_container']['normal_prompt']['interpolator_base_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Interpolator Base Field'),
      '#description' => $this->t('This is the field that will be used as context field for generating data into this field.'),
      '#options' => $baseFieldOptions,
      '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_base_field', NULL),
      '#weight' => 5,
    ];

    // Prompt if needed.
    if ($rule->needsPrompt()) {
      $form['interpolator_container']['normal_prompt']['interpolator_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Interpolator Prompt'),
        '#description' => $this->t('The prompt to use to fill this field.'),
        '#attributes' => [
          'placeholder' => $rule->placeholderText(),
        ],
        '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_prompt', ''),
        '#weight' => 10,
      ];

      // Placeholders available.
      $form['interpolator_container']['normal_prompt']['interpolator_prompt_placeholders'] = [
        '#type' => 'details',
        '#title' => $this->t('Placeholders available'),
        '#weight' => 15,
      ];

      $placeholderText = "";
      foreach ($rule->tokens() as $key => $text) {
        $placeholderText .= "<strong>{{ $key }}</strong> - " . $text . "<br>";
      }
      $form['interpolator_container']['normal_prompt']['interpolator_prompt_placeholders']['placeholders'] = [
        '#markup' => $placeholderText,
      ];
    }
    else {
      // Just save empty.
      $form['interpolator_prompt'] = [
        '#value' => '',
      ];
    }
    if ($rule->advancedMode()) {
      // Prompt with token.
      $form['interpolator_container']['token_prompt'] = [
        '#type' => 'fieldset',
        '#open' => TRUE,
        '#weight' => 11,
        '#states' => [
          'visible' => [
            ':input[name="interpolator_mode"]' => [
              'value' => 'token',
            ],
          ],
        ],
      ];

      // Tokens help - static service call since module might not exist.
      if ($this->moduleHandler->moduleExists('token')) {
        $form['interpolator_container']['token_prompt']['interpolator_token'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Interpolator Prompt (Token)'),
          '#description' => $this->t('The prompt to use to fill this field.'),
          '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_token', ''),
        ];

        // Because we have to invoke this only if the module is installed, no
        // dependency injection.
        // @codingStandardsIgnoreLine @phpstan-ignore-next-line
        $form['interpolator_container']['token_prompt']['token_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
          $this->getEntityTokenType($entity->getEntityTypeId()),
          'current-user',
        ]);
      }
    }

    $form['interpolator_container']['interpolator_edit_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit when changed'),
      '#description' => $this->t('By default the initial value or manual set value will not be overriden. If you check this, it will override if the base text field changes its value.'),
      '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_edit_mode', ''),
      '#weight' => 20,
    ];

    $form['interpolator_container']['interpolator_advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#weight' => 25,
    ];

    $form['interpolator_container']['interpolator_advanced']['interpolator_weight'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 1000,
      '#title' => $this->t('Interpolator Weight'),
      '#description' => $this->t('If you have fields dependent on each other, you can sequentially order the processing using weights. The higher the value, the later it is run.'),
      '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_weight', 100),
    ];

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processes->getDefinitions() as $definition) {
      $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
    }

    $form['interpolator_container']['interpolator_advanced']['interpolator_worker_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Interpolator Worker'),
      '#options' => $workerOptions,
      '#description' => $this->t('This defines how the saving of an interpolation happens. Direct saving is the easiest, but since it can take time you need to have longer timeouts.'),
      '#default_value' => $fieldInfo->getThirdPartySetting('ai_interpolator', 'interpolator_worker_type', 'direct'),
    ];

    $form['interpolator_container']['interpolator_advanced'] = array_merge($form['interpolator_container']['interpolator_advanced'], $rule->extraAdvancedFormFields($entity, $fieldInfo));

    // Validate.
    $form['#validate'][] = [$this, 'validateConfigValues'];
    // Save.
    $form['#entity_builders'][] = [$this, 'addConfigValues'];
  }

  /**
   * Updates the config form with the chosen rule.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function updateRule(array &$form, FormStateInterface $formState) {
    return $form['interpolator_container'];
  }

  /**
   * Validates the field config form.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function validateConfigValues(&$form, FormStateInterface $formState) {
    // Find the rule. If not found don't do anything.
    $rule = $this->fieldRules->findRule($formState->getValue('interpolator_rule'));

    // Validate the configuration.
    if ($rule->needsPrompt() && $formState->getValue('interpolator_enabled') && $formState->getValue('interpolator_mode') == 'base' && !$formState->getValue('interpolator_prompt')) {
      $formState->setErrorByName('interpolator_prompt', $this->t('If you enable AI Interpolator, you have to give a prompt.'));
    }
    if ($formState->getValue('interpolator_enabled') && $formState->getValue('interpolator_mode') == 'base' && !$formState->getValue('interpolator_base_field')) {
      $formState->setErrorByName('interpolator_base_field', $this->t('If you enable AI Interpolator, you have to give a base field.'));
    }
    // Run the rule validation.
    if (method_exists($rule, 'validateConfigValues')) {
      $rule->validateConfigValues($form, $formState);
    }
    return TRUE;
  }

  /**
   * Builds the field config.
   *
   * @param string $entity_type
   *   The entity type being used.
   * @param \Drupal\field\Entity\FieldConfig|\Drupal\Core\Field\Entity\BaseFieldOverride $fieldConfig
   *   The field config.
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function addConfigValues($entity_type, FieldConfig|BaseFieldOverride $fieldConfig, &$form, FormStateInterface $formState) {
    // Reset previous values.
    $settings = $fieldConfig->getThirdPartySettings('ai_interpolator');
    foreach ($settings as $key => $val) {
      $fieldConfig->unsetThirdPartySetting('ai_interpolator', $key);
    }
    // Save the configuration.
    if ($formState->getValue('interpolator_enabled')) {
      foreach ($formState->getValues() as $key => $val) {
        if (substr($key, 0, 13) == 'interpolator_') {
          $fieldConfig->setThirdPartySetting('ai_interpolator', $key, $val);
        }
      }
    }
    else {
      // Hard disable.
      $fieldConfig->setThirdPartySetting('ai_interpolator', 'interpolator_enabled', FALSE);
    }
    return TRUE;
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  public function getEntityTokenType($entityTypeId) {
    switch ($entityTypeId) {
      case 'taxonomy_term':
        return 'term';
    }
    return $entityTypeId;
  }

}
