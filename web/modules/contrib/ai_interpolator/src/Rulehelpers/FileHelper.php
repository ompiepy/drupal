<?php

namespace Drupal\ai_interpolator\Rulehelpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Helper functions for generating and storing files.
 */
class FileHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityTypeManager;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The file repository.
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
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system interface.
   * @param \Drupal\file\FileRepositoryInterface $fileRepo
   *   The file repository.
   * @param \Drupal\Core\Utility\Token $token
   *   The token system.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem,
    FileRepositoryInterface $fileRepo,
    Token $token,
    AccountProxyInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->fileRepo = $fileRepo;
    $this->token = $token;
    $this->currentUser = $currentUser;
  }

  /**
   * Create filepath from field config.
   *
   * @param string $fileName
   *   The file name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The file path.
   */
  public function createFilePathFromFieldConfig($fileName, FieldDefinitionInterface $fieldDefinition, ContentEntityInterface $entity) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    return $this->token->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/')) . '/' . $fileName;
  }

  /**
   * Prepare image file entity from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $dest
   *   The destination.
   *
   * @return array
   *   The image entity with meta data.
   */
  public function generateImageMetaDataFromBinary(string $binary, string $dest) {
    $file = $this->generateFileFromBinary($binary, $dest);
    if ($file instanceof FileInterface) {
      // Get resolution.
      $resolution = getimagesize($file->uri->value);
      // Add to the entities saved.
      return [
        'target_id' => $file->id(),
        'width' => $resolution[0],
        'height' => $resolution[1],
      ];
    }
    return NULL;
  }

  /**
   * Generate a file entity from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $dest
   *   The destination.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  public function generateFileFromBinary(string $binary, string $dest) {
    $path = substr($dest, 0, -(strlen($dest) + 1));
    // Create directory if not existsing.
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    $file = $this->fileRepo->writeData($binary, $dest, FileSystemInterface::EXISTS_RENAME);
    if ($file->save()) {
      return $file;
    }
    return FALSE;
  }

  /**
   * Generate a temporary file from a binary.
   *
   * @param string $binary
   *   The binary string.
   * @param string $fileType
   *   The file type.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file or false on failure.
   */
  public function generateTemporaryFileFromBinary(string $binary, $fileType = '') {
    $tmpName = $this->fileSystem->tempnam('temporary://', 'ai_interpolator_');
    if ($fileType) {
      // Delete and generate with a extension.
      unlink($tmpName);
      $tmpName .= '.' . $fileType;
    }
    $file = $this->fileRepo->writeData($binary, $tmpName, FileSystemInterface::EXISTS_RENAME);
    if ($file->save()) {
      return $file;
    }
    return FALSE;
  }

}
