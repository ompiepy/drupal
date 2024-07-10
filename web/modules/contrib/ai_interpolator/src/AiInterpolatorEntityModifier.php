<?php

namespace Drupal\ai_interpolator;

use Drupal\ai_interpolator\Event\InterpolatorConfigEvent;
use Drupal\ai_interpolator\Event\ProcessFieldEvent;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldProcessManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper for entity saving logic.
 */
class AiInterpolatorEntityModifier {

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The process manager.
   */
  protected AiInterpolatorFieldProcessManager $processes;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs an entity modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\ai_interpolator\AiFieldRules $aiFieldRules
   *   The field rules.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiInterpolatorFieldProcessManager $processes, AiFieldRules $aiFieldRules, EventDispatcherInterface $eventDispatcher) {
    $this->fieldManager = $fieldManager;
    $this->processes = $processes;
    $this->fieldRules = $aiFieldRules;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Field form should have form altered to allow automatic content generation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param bool $isInsert
   *   Is it an insert.
   *
   * @return bool
   *   If the entity is saved or marked for saving.
   */
  public function saveEntity(EntityInterface $entity, $isInsert = FALSE) {
    // Only run on Content Interfaces.
    if (!($entity instanceof ContentEntityInterface)) {
      return FALSE;
    }
    // Get and check so field configs exists.
    $configs = $this->entityHasConfig($entity);
    if (!count($configs)) {
      return FALSE;
    }

    // Resort on weight to create in the right order.
    usort($configs, function ($a, $b) {
      if ($a['interpolatorConfig']['weight'] > $b['interpolatorConfig']['weight']) {
        return 1;
      }
      elseif ($a['interpolatorConfig']['weight'] < $b['interpolatorConfig']['weight']) {
        return -1;
      }
      return 0;
    });

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processes->getDefinitions() as $definition) {
      $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
    }

    // Get process for this entity.
    $processes = $this->getProcesses($configs);

    // Preprocess.
    foreach ($processes as $process) {
      $process->preProcessing($entity);
    }

    // Walk through the fields and check if we need to save anything.
    foreach ($configs as $config) {
      // Event where you can change the field configs when something exists.
      if (!empty($config['interpolatorConfig'])) {
        $event = new InterpolatorConfigEvent($entity, $config['interpolatorConfig']);
        $this->eventDispatcher->dispatch($event, InterpolatorConfigEvent::EVENT_NAME);
        $config['interpolatorConfig'] = $event->getInterpolatorConfig();
      }
      // Load the processor or load direct.
      $processor = $processes[$config['interpolatorConfig']['worker_type']] ?? $processes['direct'];
      if (method_exists($processor, 'isImport') && $isInsert) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['interpolatorConfig'], $processor);
      }
      if (!method_exists($processor, 'isImport') && !$isInsert) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['interpolatorConfig'], $processor);
      }
    }

    // Postprocess.
    foreach ($processes as $process) {
      $process->postProcessing($entity);
    }
    return TRUE;
  }

  /**
   * Checks if an entity has fields with OpenAI interpolator enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   *
   * @return array
   *   An array with the field configs affected.
   */
  public function entityHasConfig(EntityInterface $entity) {
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $fieldConfigs = [];
    $interpolatorConfig = [];
    foreach ($fieldDefinitions as $fieldDefinition) {
      // Check if enabled and return the config.
      $config = $fieldDefinition->getConfig($entity->bundle());
      if ($config->getThirdPartySetting('ai_interpolator', 'interpolator_enabled', 0)) {
        $fieldConfigs[$config->getName()]['fieldDefinition'] = $fieldDefinition;
        $interpolatorConfig = [
          'field_name' => $config->getName(),
        ];
        foreach ($config->getThirdPartySettings('ai_interpolator') as $key => $setting) {
          $interpolatorConfig[substr($key, 13)] = $setting;
        }
        $fieldConfigs[$config->getName()]['interpolatorConfig'] = $interpolatorConfig;
      }
    }

    return $fieldConfigs;
  }

  /**
   * Gets the processes available.
   *
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   Array of processes keyed by id.
   */
  public function getProcesses(array $configs) {
    // Get possible processes.
    $processes = [];
    foreach ($configs as $config) {
      $definition = $this->processes->getDefinition($config['interpolatorConfig']['worker_type']);
      $processes[$definition['id']] = $this->processes->createInstance($definition['id']);
    }
    return $processes;
  }

  /**
   * Checks if a field should be saved and saves it appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The OpenAI Interpolator settings for the field.
   * @param \Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface $processor
   *   The processor.
   *
   * @return bool
   *   If the saving was successful or not.
   */
  protected function markFieldForProcessing(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, AiInterpolatorFieldProcessInterface $processor) {
    // Event to modify if the field should be processed.
    $event = new ProcessFieldEvent($entity, $fieldDefinition, $interpolatorConfig);
    $this->eventDispatcher->dispatch($event, ProcessFieldEvent::EVENT_NAME);
    // If a force reject or force process exists, we do that.
    if (in_array(ProcessFieldEvent::FIELD_FORCE_SKIP, $event->actions)) {
      return FALSE;
    }
    elseif (in_array(ProcessFieldEvent::FIELD_FORCE_PROCESS, $event->actions)) {
      return $processor->modify($entity, $fieldDefinition, $interpolatorConfig);
    }

    // Otherwise continue as normal.
    if ((!isset($interpolatorConfig['mode']) || $interpolatorConfig['mode'] == 'base') && !$this->baseShouldSave($entity, $interpolatorConfig)) {
      return FALSE;
    }
    elseif (isset($interpolatorConfig['mode']) && $interpolatorConfig['mode'] == 'token' && !$this->tokenShouldSave($entity, $interpolatorConfig)) {
      return FALSE;
    }

    return $processor->modify($entity, $fieldDefinition, $interpolatorConfig);
  }

  /**
   * If token mode, check if it should run.
   */
  private function tokenShouldSave(ContentEntityInterface $entity, array $interpolatorConfig) {
    // Check if a value exists.
    $value = $entity->get($interpolatorConfig['field_name'])->getValue();
    // Get prompt.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * If base mode, check if it should run.
   */
  private function baseShouldSave(ContentEntityInterface $entity, array $interpolatorConfig) {
    // Check if a value exists.
    $value = $entity->get($interpolatorConfig['field_name'])->getValue();

    $original = isset($entity->original) && json_encode($entity->original->get($interpolatorConfig['base_field'])->getValue()) ?? NULL;
    $change = json_encode($entity->get($interpolatorConfig['base_field'])->getValue()) !== $original;

    // Get the rule to check the value.
    $rule = $this->fieldRules->findRule($interpolatorConfig['rule']);
    $value = $rule->checkIfEmpty($value);

    // If the base field is not filled out.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    // If the value exists and we don't have edit mode, we do nothing.
    if (!empty($value) && !empty($value[0]) && !$interpolatorConfig['edit_mode']) {
      return FALSE;
    }
    // Otherwise look for a change.
    if ($interpolatorConfig['edit_mode'] && !$change && !empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

}
