<?php

namespace Drupal\ai_interpolator\Plugin\AiInterpolatorProcess;

use Drupal\ai_interpolator\AiInterpolatorRuleRunner;
use Drupal\ai_interpolator\AiInterpolatorStatusField;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The batch processor.
 *
 * @AiInterpolatorProcessRule(
 *   id = "batch",
 *   title = @Translation("Batch"),
 *   description = @Translation("Uses JavaScript batch queue (not recommended), will not work on programatical saving."),
 * )
 */
class BatchProcessing implements AiInterpolatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * The batch.
   */
  protected array $batch;

  /**
   * AI Runner.
   */
  protected AiInterpolatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructor.
   */
  final public function __construct(AiInterpolatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_interpolator.rule_runner'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $entry = [
      'entity' => $entity,
      'fieldDefinition' => $fieldDefinition,
      'interpolatorConfig' => $interpolatorConfig,
    ];

    $this->batch[] = [
      'Drupal\ai_interpolator\Batch\ProcessField::saveField',
      [$entry],
    ];
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
    $entity->ai_interpolator_status = AiInterpolatorStatusField::STATUS_PROCESSING;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
    if (!empty($this->batch)) {
      $batch = [
        'operations' => $this->batch,
        'title' => 'AI Interpolator',
        'init_message' => 'Processing AI fields.',
        'progress_message' => 'Processed @current out of @total.',
        'error_message' => 'Something went wrong.',
      ];
      \batch_set($batch);
    }
  }

}
