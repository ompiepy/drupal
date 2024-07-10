<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiVideoHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Entity\File;

/**
 * The rules for a text_long field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_video_to_video",
 *   title = @Translation("OpenAI Video To Video (Experimental)"),
 *   field_rule = "file",
 *   target = "file",
 * )
 */
class OpenAiVideoToVideo extends OpenAiVideoHelper implements AiInterpolatorFieldRuleInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Video Extraction (Experimental)';

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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form['interpolator_cutting_prompt'] = [
      '#type' => 'textarea',
      '#title' => 'Cutting Prompt',
      '#description' => $this->t('Any commands that you need to give to cut out the video(s). Specify if you want the video(s) to be mixed together in one video if you only want one video out. Can use Tokens if Token module is installed.'),
      '#attributes' => [
        'placeholder' => $this->t('Cut out all the videos where they are saying "Hello". Mix together in one video.'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_cutting_prompt', ''),
      '#weight' => 24,
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      // Because we have to invoke this only if the module is installed, no
      // dependency injection.
      // @codingStandardsIgnoreLine @phpstan-ignore-next-line
      $form['interpolator_cutting_prompt_token_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
        $this->getEntityTokenType($entity->getEntityTypeId()),
        'current-user',
      ]);
      $form['interpolator_cutting_prompt_token_help']['#weight'] = 25;
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Don't get the base form.
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    // Tokenize prompt.
    $cutPrompt = $this->renderTokenPrompt($interpolatorConfig['cutting_prompt'], $entity);

    $total = [];
    foreach ($entity->{$interpolatorConfig['base_field']} as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'video/mp4',
        ])) {
          $this->prepareToExplain($entityWrapper->entity);
          $prompt = "The following images shows rasters of scenes from a video together with a timestamp when it happens in the video. The audio is transcribed below. Please follow the instructions below with the video as context, using images and transcripts and try to figure out what sections the person wants to cut out. Unless the persons specifices that they want the video mixed together in one video, give back multiple timestamps if needed. If the don't want it mixed, give back multiple values with just one start time and end time.\n\n";
          $prompt .= "Instructions:\n----------------------------\n" . $cutPrompt . "\n----------------------------\n\n";
          $prompt .= "Transcription:\n----------------------------\n" . $this->transcription . "\n----------------------------\n\n";
          $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": [{\"start_time\": \"The start time of the cut in format h:i:s.ms\", \"end_time\": \"The end time of the cut in format h:i:s.ms\"}]].";
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
    // Should have start and end time.
    if (!is_array($value) && !isset($value[0]['start_time']) && !isset($value[0]['end_time'])) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $files = [];
    // Create a tmp directory.
    $this->createTempDirectory();

    // First cut out the videos.
    $baseField = $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_base_field', '');
    $realPath = $this->fileSystem->realpath($entity->{$baseField}->entity->getFileUri());
    // Get the actual file name and replace it with _cut.
    $fileName = pathinfo($realPath, PATHINFO_FILENAME);
    $newFile = str_replace($fileName, $fileName . '_cut', $entity->{$baseField}->entity->getFileUri());

    foreach ($values as $keys) {
      $tmpNames = [];
      foreach ($keys as $key) {
        // Generate double files, but we only need the last one.
        $tmpName = $this->fileSystem->tempnam($this->tmpDir, 'video') . '.mp4';
        $tmpNames[] = $tmpName;

        $command = "ffmpeg -y -nostdin -i \"$realPath\" -ss {$key['start_time']} -to {$key['end_time']} -c:v libx264 -c:a aac -strict -2 $tmpName";
        exec($command, $status);
        if ($status) {
          throw new AiInterpolatorResponseErrorException('Could not generate new videos.');
        }
      }

      // If we only have one video, we can just rename it.
      if (count($tmpNames) == 1) {
        $endFile = $tmpNames[0];
      }
      else {
        // If we have more than one video, we need to mix them together.
        $endFile = $this->fileSystem->tempnam($this->tmpDir, 'video') . '.mp4';
        // Generate list file.
        $text = '';
        foreach ($tmpNames as $tmpName) {
          $text .= "file '$tmpName'\n";
        }
        file_put_contents($this->tmpDir . 'list.txt', $text);
        $command = "ffmpeg -y -nostdin -f concat -safe 0 -i {$this->tmpDir}list.txt -c:v libx264 -c:a aac -strict -2 $endFile";
        exec($command, $status);
        if ($status) {
          throw new AiInterpolatorResponseErrorException('Could not generate new videos.');
        }
      }
      // Move the file to the correct place.
      $fixedFile = $this->fileSystem->move($endFile, $newFile);

      // Generate the new file entity.
      $file = File::create([
        'uri' => $fixedFile,
        'status' => 1,
        'uid' => $this->currentUser->id(),
      ]);
      $file->save();
      $files[] = ['target_id' => $file->id()];
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $files);
    return TRUE;
  }

}
