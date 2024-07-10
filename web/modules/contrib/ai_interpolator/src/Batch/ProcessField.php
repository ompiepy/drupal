<?php

namespace Drupal\ai_interpolator\Batch;

use Drupal\ai_interpolator\Exceptions\AiInterpolatorRequestErrorException;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorRuleNotFoundException;

/**
 * Processing a field in batch mode.
 */
class ProcessField {

  /**
   * Save the field.
   *
   * @param array $data
   *   The data needed.
   */
  public static function saveField(array $data) {
    $logger = \Drupal::logger('ai_interpolator');
    try {
      // Get new entity, to not overwrite.
      $newEntity = \Drupal::entityTypeManager()->getStorage($data['entity']->getEntityTypeId())->load($data['entity']->id());
      $entity = \Drupal::service('ai_interpolator.rule_runner')->generateResponse($newEntity, $data['fieldDefinition'], $data['interpolatorConfig']);
      // Turn off the hook.
      _ai_interpolator_entity_can_save_toggle(FALSE);
      // Resave.
      $entity->save();
      // Turn on the hook.
      _ai_interpolator_entity_can_save_toggle(TRUE);
      $logger->info("Saved via batch job the entity %id of type %entity_type on field %field_name", [
        '%id' => $data['entity']->id(),
        '%entity_type' => $data['entity']->getEntityTypeId(),
        '%field_name' => $data['fieldDefinition']->getName(),
      ]);
      return;
    }
    catch (AiInterpolatorRuleNotFoundException $e) {
      $logger->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiInterpolatorRequestErrorException $e) {
      $logger->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiInterpolatorResponseErrorException $e) {
      $logger->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $logger->warning('A general error happened why trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    \Drupal::messenger()->addWarning($e->getMessage());
  }

}
