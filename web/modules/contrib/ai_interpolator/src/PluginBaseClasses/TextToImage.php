<?php

namespace Drupal\ai_interpolator\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for image generators.
 */
class TextToImage extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate images from text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "{{ context }}, 50mm portrait photography, hard rim lighting photography-beta";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($interpolatorConfig['mode']) && $interpolatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_interpolator.prompt_helper')->renderTokenPrompt($interpolatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($interpolatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $interpolatorConfig, $i);
        $prompts[] = \Drupal::service('ai_interpolator.prompt_helper')->renderPrompt($interpolatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Get amount if it exists.
    $amount = $interpolatorConfig['image_generation_amount'] ?? 1;

    // Generate the images.
    $images = [];
    foreach ($prompts as $prompt) {
      // The image binary.
      for ($i = 0; $i < $amount; $i++) {
        $image = $this->generateResponse($prompt, $interpolatorConfig, $entity, $fieldDefinition);
        if ($image) {
          $images[] = [
            'filename' => $this->getFileName($interpolatorConfig),
            'binary' => $image,
          ];
        }
      }
    }
    return $images;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    if (!isset($value['filename'])) {
      return FALSE;
    }
    // Detect if binary.
    return preg_match('~[^\x20-\x7E\t\r\n]~', $value['binary']) > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $images = [];
    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $fieldDefinition, $entity);
      $images[] = $fileHelper->generateImageMetaDataFromBinary($value['binary'], $path);
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $images);
    return TRUE;
  }

  /**
   * Gets the filename. Override this.
   *
   * @param array $args
   *   If arguments are needed to create the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFileName(array $args = []) {
    return 'ai_generated.jpg';
  }

  /**
   * Gets the file helper.
   *
   * @return \Drupal\ai_interpolator\Rulehelpers\FileHelper
   *   The file helper.
   */
  public function getFileHelper() {
    return \Drupal::service('ai_interpolator.rule_helper.file');
  }

}
