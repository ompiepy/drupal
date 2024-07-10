<?php

namespace Drupal\ai_interpolator;

use Drupal\ai_interpolator\Event\RuleIsAllowedEvent;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldRuleManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper to find and use field rules.
 */
class AiFieldRules {

  /**
   * The field rule manager.
   */
  protected AiInterpolatorFieldRuleManager $fieldRuleManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a field rule manager.
   *
   * @param \Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldRuleManager $fieldRuleManager
   *   The field rule manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(AiInterpolatorFieldRuleManager $fieldRuleManager, EventDispatcherInterface $eventDispatcher) {
    $this->fieldRuleManager = $fieldRuleManager;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Find all possible definitions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return array[Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule]
   *   The field rules to possibly use.
   */
  public function findRuleCandidates(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $target = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type'] ?? NULL;
    $candidates = [];
    foreach ($this->fieldRuleManager->getDefinitions() as $definition) {
      if ($definition['field_rule'] == $fieldDefinition->getType() && (
        !$target || $definition['target'] == $target)) {
        $rule = $this->fieldRuleManager->createInstance($definition['id']);
        // Event that can be used to override the rule.
        $event = new RuleIsAllowedEvent($entity, $fieldDefinition);
        $this->eventDispatcher->dispatch($event, RuleIsAllowedEvent::EVENT_NAME);
        // If a force reject is in place, skip.
        if (in_array(RuleIsAllowedEvent::RULE_FORCE_HIDDEN, $event->actions)) {
          continue;
        }
        // Otherwise follow the rule or force visible.
        elseif (in_array(RuleIsAllowedEvent::RULE_FORCE_VISIBLE, $event->actions) || $rule->ruleIsAllowed($entity, $fieldDefinition)) {
          $candidates[$definition['id']] = $rule;
        }
      }
    }
    return $candidates;
  }

  /**
   * Find definition.
   *
   * @param string $id
   *   The id of the rule.
   *
   * @return Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule
   *   The field rule to use.
   */
  public function findRule($id) {
    foreach ($this->fieldRuleManager->getDefinitions() as $definition) {
      if ($id == $definition['id']) {
        return $this->fieldRuleManager->createInstance($definition['id']);
      }
    }
    return NULL;
  }

}
