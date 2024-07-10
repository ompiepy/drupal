<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiRequester;
use Drupal\ai_interpolator_openai\OpenAiVideoHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for a text_long field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_video_to_text",
 *   title = @Translation("OpenAI Video To Text (Experimental)"),
 *   field_rule = "text_long"
 * )
 */
class OpenAiVideoToText extends OpenAiVideoHelper implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Video To Text (Experimental)';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
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
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Checks system for ffmpeg, otherwise this rule does not exist.
    $command = (PHP_OS == 'WINNT') ? 'where ffmpeg' : 'which ffmpeg';
    $result = shell_exec($command);
    return $result ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function tokens() {
    return [];
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

    // Offer to upload an image.
    $form['interpolator_openai_video_output'] = [
      '#type' => 'select',
      '#options' => [
        'both' => $this->t('Both Video and Audio'),
        'video' => $this->t('Video Only'),
        'audio' => $this->t('Audio Only'),
      ],
      '#title' => 'Source from video',
      '#description' => $this->t('If you only want the audio or the video to be the source, you can specify it here.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_video_output', 'both'),
      '#weight' => 24,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {

    $total = [];
    foreach ($entity->{$interpolatorConfig['base_field']} as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'video/mp4',
        ])) {
          $this->prepareToExplain($entityWrapper->entity);
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below. Please follow the instructions below with the video as context, using images and transcripts.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $interpolatorConfig['prompt'] . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": \"requested value\"}].";
          $values = $this->openAi->generateResponse($prompt, $fieldDefinition, [
            'openai_model' => 'gpt-4-vision-preview',
          ], $this->images);
          $total = array_merge_recursive($total, $values);
        }
      }
    }
    return $total;
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
