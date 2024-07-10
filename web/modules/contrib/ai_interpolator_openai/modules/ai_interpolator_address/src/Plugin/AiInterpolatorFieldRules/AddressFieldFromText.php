<?php

namespace Drupal\ai_interpolator_address\Plugin\AiInterPolatorFieldRules;

use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator_address\GooglePlacesApi;
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
 * The rules for an address field.
 *
 * @AiInterpolatorFieldRule(
 *   id = "ai_interpolator_address_text",
 *   title = @Translation("Text Address Finder"),
 *   field_rule = "address"
 * )
 */
class AddressFieldFromText extends OpenAiBase implements AiInterpolatorFieldRuleInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Text Address Finder';

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityManager;

  /**
   * The OpenAI requester.
   */
  public OpenAiRequester $openAiRequester;

  /**
   * The Google Places API.
   */
  public GooglePlacesApi $googlePlacesApi;

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
   * @param \Drupal\ai_interpolator_address\GooglePlacesApi $googlePlacesApi
   *   The Google Places requester.
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
    GooglePlacesApi $googlePlacesApi,
    FileSystemInterface $fileSystem,
    FileRepositoryInterface $fileRepo,
    Token $token,
    AccountProxyInterface $currentUser,
    LoggerChannelFactoryInterface $loggerChannel,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $openAiRequester);
    $this->googlePlacesApi = $googlePlacesApi;
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
      $container->get('ai_interpolator_address.google_places_api'),
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
    return $this->t("Scrape data for addresses and give them back in a structured format. The prompt has to always answer with searching for places.");
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "From the context text, find all geographical places that exists that can be plotted on Google Maps and give them back as something that would give a credible answer in Google maps. Add city and country if you know it. Try to figure out country from the context. Also give the title of the location.\n\nContext:\n{{ context }}";
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
      // Add to get functional output.
      $prompt .= "\n-------------------------------------\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"search_text\": \"The search text for Google Maps\", \"title\": \"A title of the location, like a company name, a country name or a persons name.\"}}]\n\n";
      $prompt .= "Examples would be:\n";
      $prompt .= "[{\"value\": {\"search_text\": \"Radisson Collection Hotel, Berlin, Germany\", \"title\": \"Radisson Collection Hotel\"}},{\"value\": {\"search_text\": \"Spandauer Straße, Berlin, Germany\", \"title\": \"Spandauer Straße\"}}]\n";
      $prompt .= "[{\"value\": {\"search_text\": \"Gothenburg, Sweden\", \"title\": \"Gothenburg\"}},{\"value\": {\"search_text\": \"Sannegårdens Pizzeria Johanneberg, Gibraltargatan 52, 412 58 Göteborg, Sweden\", \"title\": \"Sannegårdens Pizzeria Johanneberg\"}}]\n";
      $prompt .= "[{\"value\": {\"search_text\": \"Oliver Schrott Kommunikation offices, Germany\", \"title\": \"Oliver Schrott Kommunikation offices\"}},{\"value\": {\"search_text\": \"Sannegårdens Pizzeria Johanneberg, Gibraltargatan 52, 412 58 Göteborg, Sweden\", \"title\": \"Sannegårdens Pizzeria Johanneberg\"}}]\n";

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
    if ($value) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition) {

    $addresses = [];

    foreach ($values as $value) {
      if (isset($value['title']) && isset($value['search_text'])) {
        $place = $this->googlePlacesApi->getPlaceInfo($value['title'], $value['search_text']);

        if (isset($place['result']['address_components'])) {
          $renderList = [];
          foreach ($place['result']['address_components'] as $part) {
            $renderList[$part['types'][0]] = [
              'long_name' => $part['long_name'],
              'short_name' => $part['short_name'],
            ];
          }

          if (!empty($renderList['country']['short_name'])) {
            $street = $renderList['route']['long_name'] ?? '';
            if ($street && !empty($renderList['street_number']['long_name'])) {
              $street .= ' ' . $renderList['street_number']['long_name'];
            }

            $addresses[] = [
              'country_code' => $renderList['country']['short_name'] ?? '',
              'administrative_area' => $renderList['administrative_area_level_1']['long_name'] ?? '',
              'locality' => $renderList['locality']['long_name'] ?? '',
              'postal_code' => $renderList['postal_code']['long_name'] ?? '',
              'sorting_code' => $renderList['sorting_code']['long_name'] ?? '',
              'address_line1' => $street,
              'organization' => $value['title'] ?? '',
            ];
          }
        }
      }
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $addresses);
    return TRUE;
  }

}
