<?php

namespace Drupal\ai_interpolator_openai\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_openai\OpenAiBase;
use Drupal\ai_interpolator_openai\OpenAiRequester;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for an charts field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_charts_text",
 *   title = @Translation("OpenAi Charts From Text"),
 *   field_rule = "chart_config"
 * )
 */
class OpenAiChartFromText extends OpenAiBase implements AiInterpolatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'OpenAi Charts From Text';

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityManager;

  /**
   * The OpenAI requester.
   */
  public OpenAiRequester $openAiRequester;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The File Repo.
   */
  public FileRepositoryInterface $fileRepo;

  /**
   * The token system to replace and generate paths.
   */
  public Token $token;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * The logger channel.
   */
  public LoggerChannelFactoryInterface $loggerChannel;

  /**
   * Colors to set.
   */
  public array $colors = [
    '#006fb0',
    '#f07c33',
  ];

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
   * @param \Drupal\ai_interpolator_openai\OpenAiRequester $openAiRequester
   *   The OpenAI Requester.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File system interface.
   * @param \Drupal\file\FileRepositoryInterface $fileRepo
   *   The File repo.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannel
   *   The logger channel interface.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityManager,
    OpenAiRequester $openAiRequester,
    FileSystemInterface $fileSystem,
    FileRepositoryInterface $fileRepo,
    Token $token,
    AccountProxyInterface $currentUser,
    LoggerChannelFactoryInterface $loggerChannel,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $openAiRequester);
    $this->entityManager = $entityManager;
    $this->fileSystem = $fileSystem;
    $this->fileRepo = $fileRepo;
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
      $container->get('file.repository'),
      $container->get('token'),
      $container->get('current_user'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return $this->t("Scrape data for possible chart data and render it.");
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "From the context text, use the mobile phones name, the weight and RAM as values.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value) {
    if (empty($value[0]['config']['series']['data_collector_table'][0][0]['data'])) {
      return [];
    }
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $interpolatorConfig);
    $images = $interpolatorConfig['openai_vision_images'] ? $this->getVisionImages($interpolatorConfig['openai_vision_images'], $entity) : [];

    $total = [];
    // Add to get functional output.
    foreach ($prompts as $prompt) {
      $prompt .= "\n-------------------------------------\n\nDo not include any explanations, only provide a CSV file withe keys in the first row and the values in the second rows without deviation. The keys should have the prefix or suffix in paranthesis and the value should be stripped of it.\n\n";
      $prompt .= "Examples would be:\n";
      $prompt .= "\"Hotel Name\"; \"Max Capacity (people)\"; \"Hotel Size (sqm)\"\n";
      $prompt .= "\"Hotel Radisson, Berlin\"; 300; 1280\n";
      $prompt .= "\"The Vichy, Jamestown\"; 840; 3880\n";
      try {
        $values = $this->openAi->generateResponse($prompt, $fieldDefinition, $interpolatorConfig, $images);
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
    if (empty(str_getcsv($value))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {
    $defaults = $entity->get($fieldDefinition->getName())->getValue();
    $cols = explode("\n", $values[0]);
    $data = [];
    foreach ($cols as $colKey => $col) {
      $rows = explode(";", $col);
      foreach ($rows as $rowKey => $row) {
        $row = trim(trim($row), '"');
        $data[$colKey][$rowKey]['data'] = $row;
        if ($colKey == 0 && $rowKey > 0) {
          $data[$colKey][$rowKey]['color'] = $this->colors[($rowKey - 1)];
        }
      }
    }
    $defaults[0]['config']['series']['data_collector_table'] = $data;

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $defaults);
    return TRUE;
  }

}
