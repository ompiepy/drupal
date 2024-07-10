<?php

namespace Drupal\ai_interpolator\Plugin\AiInterpolatorProcess;

use Drupal\ai_interpolator\AiInterpolatorRuleRunner;
use Drupal\ai_interpolator\AiInterpolatorStatusField;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorRequestErrorException;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorRuleNotFoundException;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldProcessInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The direct processor.
 *
 * @AiInterpolatorProcessRule(
 *   id = "direct",
 *   title = @Translation("Direct"),
 *   description = @Translation("Processes and saves the value directly."),
 * )
 */
class DirectSaveProcessing implements AiInterpolatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * Direct Saving.
   */
  protected AiInterpolatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Drupal messenger service.
   */
  protected Messenger $messenger;

  /**
   * Constructor.
   */
  final public function __construct(AiInterpolatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger, Messenger $messenger) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_interpolator.rule_runner'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    try {
      return $this->aiRunner->generateResponse($entity, $fieldDefinition, $interpolatorConfig);
    }
    catch (AiInterpolatorRuleNotFoundException $e) {
      $this->loggerFactory->get('ai_interpolator')->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiInterpolatorRequestErrorException $e) {
      $this->loggerFactory->get('ai_interpolator')->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiInterpolatorResponseErrorException $e) {
      $this->loggerFactory->get('ai_interpolator')->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_interpolator')->warning('A general error happened why trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    $this->messenger->addWarning($e->getMessage());
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
    $entity->ai_interpolator_status = AiInterpolatorStatusField::STATUS_FINISHED;
  }

}
