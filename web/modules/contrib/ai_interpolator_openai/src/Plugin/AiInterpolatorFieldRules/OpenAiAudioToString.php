<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The rules for a string_long field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_audio_to_string",
 *   title = @Translation("OpenAI Audio To Text"),
 *   field_rule = "string_long"
 * )
 */
class OpenAiAudioToString extends OpenAiBase implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Audio To Text';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form = [];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $values = [];
    foreach ($entity->{$interpolatorConfig['base_field']} as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'audio/mpeg',
          'audio/aac',
          'audio/wav',
        ])) {
          $input = [
            'model' => 'whisper-1',
            'file' => fopen($fileEntity->getFileUri(), 'r'),
            'response_format' => 'json',
          ];
          $return = $this->openAi->transcribe($input);
          if (!empty($return['text'])) {
            $values[] = $return['text'];
          }
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    // Should be a string.
    if (!is_string($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
