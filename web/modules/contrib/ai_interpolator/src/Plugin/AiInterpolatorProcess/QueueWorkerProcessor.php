<?php

namespace Drupal\ai_interpolator\Plugin\AiInterpolatorProcess;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The queue processor.
 *
 * @AiInterpolatorProcessRule(
 *   id = "queue",
 *   title = @Translation("Queue/Cron"),
 *   description = @Translation("Saves as a queue worker and runs on cron."),
 * )
 */
class QueueWorkerProcessor implements AiInterpolatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * A queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructor.
   */
  final public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('queue'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $queue = $this->queueFactory->get('ai_interpolator_field_modifier');
    $queue->createItem([
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'fieldDefinition' => $fieldDefinition,
      'interpolatorConfig' => $interpolatorConfig,
    ]);
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
  }

  /**
   * Should run on import.
   */
  public function isImport() {
    return TRUE;
  }

}
