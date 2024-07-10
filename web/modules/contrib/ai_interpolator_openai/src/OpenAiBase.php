<?php

namespace Drupal\ai_interpolator_openai;

use Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base for OpenAI fields.
 */
class OpenAiBase extends AiInterpolatorFieldRule implements AiInterpolatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI Boolean';

  /**
   * The OpenAI requester.
   */
  public OpenAiRequester $openAi;

  /**
   * Construct a boolean field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\ai_interpolator_openai\OpenAiRequester $openAi
   *   The OpenAI requester.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OpenAiRequester $openAi) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->openAi = $openAi;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_interpolator_openai.request')
    );
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
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {

    $form['interpolator_openai_model'] = [
      '#type' => 'select',
      '#title' => 'OpenAI Model',
      '#description' => $this->t('Choose the model you want to use here.'),
      '#options' => $this->openAi->getModels(),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_model', 'gpt-3.5-turbo'),
      '#weight' => 24,
    ];

    // Offer to upload an image.
    $options = ['' => 'No image field'] + $this->getFieldsOfType($entity, 'image');
    $form['interpolator_openai_vision_images'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => 'OpenAI Vision Image',
      '#description' => $this->t('A image field to use for OpenAI Vision.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_vision_images', ''),
      '#weight' => 24,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_openai_model"]' => [
            'value' => 'gpt-4-vision-preview',
          ],
        ],
      ],
    ];

    $form['interpolator_openai_role'] = [
      '#type' => 'textarea',
      '#title' => 'OpenAI Role',
      '#description' => $this->t('If the AI should have some specific role, write it here.'),
      '#attributes' => [
        'placeholder' => $this->t('A receptionist.'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_role', ''),
      '#weight' => 25,
    ];

    $form['interpolator_openai_moderation'] = [
      '#type' => 'checkbox',
      '#title' => 'OpenAI Moderation',
      '#description' => $this->t('If OpenAI should run through moderation request, before sending AI request. Highly recommended.'),
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_moderation', 1),
      '#weight' => 25,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    if (!empty($interpolatorConfig['openai_moderation'])) {
      foreach ($prompts as $key => $prompt) {
        if ($this->openAi->hasFlaggedContent($prompt, $interpolatorConfig)) {
          // Remove flagged content.
          unset($prompts[$key]);
        }
      }
    }
    return $prompts;
  }

  /**
   * Helper function to get images as base 64.
   *
   * @param string $field
   *   The field to use.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to fetch images from.
   *
   * @return array
   *   The images as base64.
   */
  public function getVisionImages($field, ContentEntityInterface $entity) {
    $images = [];
    foreach ($entity->{$field} as $data) {
      $imageEntity = $data->entity;
      $images[] = 'data:' . $imageEntity->getMimeType() . ';base64,' . base64_encode(file_get_contents($imageEntity->getFileUri()));
    }
    return $images;
  }

}
