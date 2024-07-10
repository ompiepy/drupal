<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiRequester;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for an image field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_openai_image_generation",
 *   title = @Translation("OpenAI DALL·E"),
 *   field_rule = "image",
 *   target = "file"
 * )
 */
class OpenAiImageGeneration extends AiInterpolatorFieldRule implements AiInterpolatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAI DALL·E';

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityManager;

  /**
   * The OpenAI requester.
   */
  public OpenAiRequester $openAi;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The token system to replace and generate paths.
   */
  public Token $token;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * The logger channel factory.
   */
  public LoggerChannelFactoryInterface $loggerChannel;

  /**
   * Construct an image field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager.
   * @param \Drupal\ai_interpolator_openai\OpenAiRequester $openAi
   *   The OpenAI requester.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File system interface.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannel
   *   The logger channel factory.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityManager,
    OpenAiRequester $openAi,
    FileSystemInterface $fileSystem,
    Token $token,
    AccountProxyInterface $currentUser,
    LoggerChannelFactoryInterface $loggerChannel,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $openAi);
    $this->entityManager = $entityManager;
    $this->openAi = $openAi;
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->currentUser = $currentUser;
    $this->loggerChannel = $loggerChannel;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ai_interpolator_openai.request'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('current_user'),
      $container->get('logger.factory'),
    );
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
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form['interpolator_image_description'] = [
      '#markup' => '<strong>This will create on image per prompt from the Dall-e 2 or 3 api.</strong>',
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition);
    $form['interpolator_openai_model'] = [
      '#type' => 'select',
      '#title' => 'DALL·E Engine',
      '#description' => $this->t('Choose the engine you want to use here.'),
      '#options' => [
        'dall-e-2' => $this->t('DALL·E 2'),
        'dall-e-3' => $this->t('DALL·E 3'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_model', 'dall-e-2'),
      '#weight' => 24,
    ];

    $form['interpolator_openai_quality'] = [
      '#type' => 'select',
      '#title' => 'DALL·E Quality',
      '#description' => $this->t('Choose the quality. Only for DALL·E 3.'),
      '#options' => [
        'standard' => $this->t('standard'),
        'hd' => $this->t('hd'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_quality', 'standard'),
      '#weight' => 24,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_openai_model"]' => [
            'value' => 'dall-e-3',
          ],
        ],
      ],
    ];

    $form['interpolator_openai_size_dall_e_2'] = [
      '#type' => 'select',
      '#title' => 'DALL·E 2 Size',
      '#description' => $this->t('The size to use.'),
      '#options' => [
        '256x256' => $this->t('256x256'),
        '512x512' => $this->t('512x512'),
        '1024x1024' => $this->t('1024x1024'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_size_dall_e_2', '512x512'),
      '#weight' => 24,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_openai_model"]' => [
            'value' => 'dall-e-2',
          ],
        ],
      ],
    ];

    $form['interpolator_openai_size_dall_e_3'] = [
      '#type' => 'select',
      '#title' => 'DALL·E 3 Size',
      '#description' => $this->t('The size to use.'),
      '#options' => [
        '1024x1024' => $this->t('1024x1024'),
        '1024x1792' => $this->t('1024x1792'),
        '1792x1024' => $this->t('1792x1024'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_size_dall_e_3', '1792x1024'),
      '#weight' => 24,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_openai_model"]' => [
            'value' => 'dall-e-3',
          ],
        ],
      ],
    ];

    $form['interpolator_openai_style'] = [
      '#type' => 'select',
      '#title' => 'DALL·E 3 Style',
      '#description' => $this->t('The style to use.'),
      '#options' => [
        'vivid' => $this->t('vivid'),
        'natural' => $this->t('neutral'),
      ],
      '#default_value' => $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_style', 'vivid'),
      '#weight' => 24,
      '#states' => [
        'visible' => [
          ':input[name="interpolator_openai_model"]' => [
            'value' => 'dall-e-3',
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $parameters = [
      'n' => 1,
      'response_format' => 'url',
      'model' => $interpolatorConfig['openai_model'] ?? 'dall-e-2',
    ];
    switch ($interpolatorConfig['openai_model']) {
      case 'dall-e-2':
        $parameters['size'] = $interpolatorConfig['openai_size_dall_e_2'] ?? '512x512';
        break;

      case 'dall-e-3':
        $parameters['quality'] = $interpolatorConfig['openai_quality'] ?? 'standard';
        $parameters['size'] = $interpolatorConfig['openai_size_dall_e_3'] ?? '1792x1024';
        $parameters['style'] = $interpolatorConfig['openai_style'] ?? 'vivid';
        break;
    }
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    if (!empty($interpolatorConfig['openai_moderation'])) {
      foreach ($prompts as $key => $prompt) {
        if ($this->openAi->hasFlaggedContent($prompt, $interpolatorConfig)) {
          unset($prompts[$key]);
        }
      }
    }

    $total = [];
    // Add to get functional output.
    foreach ($prompts as $prompt) {
      try {
        $parameters['prompt'] = $prompt;
        $values = $this->openAi->generateImage($parameters);
        $total = array_merge_recursive($total, $values);
      }
      catch (\Exception $e) {

      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition) {
    return filter_var($value, FILTER_VALIDATE_URL);
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    // Initial values.
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    $trashCan = [];
    $fileEntities = [];

    foreach ($values as $value) {
      $tmpFile = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'openai_image_');
      if (empty($value)) {
        continue;
      }

      // Download image to verify.
      file_put_contents($tmpFile, file_get_contents($value));
      // Add to trash.
      $trashCan[] = $tmpFile;
      // Get base name, without potential query strings.
      $fileName = basename(explode('?', $value)[0]);

      // Get the whole filepath.
      $filePath = $this->token->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/')) . '/' . $fileName;
      $file = $this->generateFileFromString($tmpFile, $filePath);
      // If we can save, we attach it.
      if ($file) {
        $fieldDefinition->getConfig($entity->bundle())->getThirdPartySetting('ai_interpolator', 'interpolator_openai_image_title', FALSE);
        // Get resolution.
        $resolution = getimagesize($file->uri->value);
        // Add to the entities saved.
        $fileEntities[] = [
          'target_id' => $file->id(),
          'width' => $resolution[0],
          'height' => $resolution[1],
        ];

      }
    }

    // Remove files.
    foreach ($trashCan as $garbageFile) {
      if (file_exists($garbageFile)) {
        unlink($garbageFile);
      }
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $fileEntities);
    return TRUE;
  }

  /**
   * Generate a file entity.
   *
   * @param string $source
   *   The source file.
   * @param string $dest
   *   The destination.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  private function generateFileFromString(string $source, string $dest) {
    // File storage.
    $fileStorage = $this->entityManager->getStorage('file');
    // Calculate path.
    $fileName = basename($dest);
    $path = substr($dest, 0, -(strlen($dest) + 1));
    // Create directory if not existsing.
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    $filePath = $this->fileSystem->copy($source, $dest, FileSystemInterface::EXISTS_RENAME);
    // Create file entity.
    $file = $fileStorage->create([
      'filename' => $fileName,
      'uri' => $filePath,
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    if ($file->save()) {
      return $file;
    }
    return FALSE;
  }

}
