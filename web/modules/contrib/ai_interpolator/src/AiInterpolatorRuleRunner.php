<?php

namespace Drupal\ai_interpolator;

use Drupal\ai_interpolator\Event\ValuesChangeEvent;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorRuleNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Run one rule.
 */
class AiInterpolatorRuleRunner {

  /**
   * The entity type manager.
   */
  protected EntityTypeManager $entityType;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a new AiInterpolatorRuleRunner object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type definition.
   * @param \Drupal\ai_interpolator\AiFieldRules $fieldRules
   *   The field rule manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManager $entityTypeManager, AiFieldRules $fieldRules, EventDispatcherInterface $eventDispatcher) {
    $this->entityType = $entityTypeManager;
    $this->fieldRules = $fieldRules;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Generate response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The interpolator config.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Throws error or returns entity.
   */
  public function generateResponse(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    // Get rule.
    $rule = $this->fieldRules->findRule($interpolatorConfig['rule']);

    if (!$rule) {
      throw new AiInterpolatorRuleNotFoundException('The rule could not be found: ' . $fieldDefinition->getType());
    }

    // Generate values.
    $values = $rule->generate($entity, $fieldDefinition, $interpolatorConfig);

    // Run event to change the values if needed.
    $event = new ValuesChangeEvent($values, $entity, $fieldDefinition, $interpolatorConfig);
    $this->eventDispatcher->dispatch($event, ValuesChangeEvent::EVENT_NAME);
    $values = $event->getValues();

    foreach ($values as $key => $value) {
      // Remove values that does not fit.
      if (!$rule->verifyValue($entity, $value, $fieldDefinition)) {
        unset($values[$key]);
      }
    }

    // Save values.
    if ($values && is_array($values)) {
      $rule->storeValues($entity, $values, $fieldDefinition);
    }
    return $entity;
  }

}
