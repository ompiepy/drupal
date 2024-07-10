<?php

namespace Drupal\ai_interpolator_openai;

use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Updater\Module;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base for OpenAI fields.
 */
class OpenAiVideoHelper extends OpenAiBase implements ContainerFactoryPluginInterface {

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
   * The temporary directory.
   */
  public string $tmpDir;

  /**
   * The images.
   */
  public array $images;

  /**
   * The transcription.
   */
  public string $transcription;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * The module handler.
   */
  public ModuleHandlerInterface $moduleHandler;

  /**
   * Construct a boolean field.
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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityManager,
    OpenAiRequester $openAi,
    FileSystemInterface $fileSystem,
    Token $token,
    ModuleHandlerInterface $moduleHandler,
    AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $openAi);
    $this->openAi = $openAi;
    $this->entityManager = $entityManager;
    $this->openAi = $openAi;
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->currentUser = $currentUser;
    $this->moduleHandler = $moduleHandler;
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
      $container->get('entity_type.manager'),
      $container->get('ai_interpolator_openai.request'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('current_user'),
    );
  }

  /**
   * Delete files.
   */
  public function __destruct() {
    if (!empty($this->tmpDir) && file_exists($this->tmpDir)) {
      exec('rm -rf ' . $this->tmpDir);
    }
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
   * Generate a video from screenshots.
   *
   * @param \Drupal\file\Entity\File $video
   *   The video.
   * @param string $timeStamp
   *   The timestamp.
   * @param array $cropData
   *   The crop data in x, y, width, height format.
   *
   * @return \Drupal\file\Entity\File
   *   The screenshot image.
   */
  public function screenshotFromTimestamp(File $video, $timeStamp, array $cropData = []) {
    $path = $video->getFileUri();
    $realPath = $this->fileSystem->realpath($path);
    $command = "ffmpeg -y -nostdin -ss $timeStamp -i \"$realPath\" -vframes 1 {$this->tmpDir}/screenshot.jpeg";
    // If we need to crop also.
    if (count($cropData)) {
      $realCropData = $this->normalizeCropData($video, $cropData);
      $command = "ffmpeg -y -nostdin  -ss $timeStamp -i \"$realPath\" -vf \"crop={$realCropData[2]}:{$realCropData[3]}:{$realCropData[0]}:{$realCropData[1]}\" -vframes 1 {$this->tmpDir}/screenshot.jpeg";
    }

    exec($command, $status);
    if ($status) {
      throw new AiInterpolatorResponseErrorException('Could not create video screenshot.');
    }
    $newFile = str_replace($video->getFilename(), $video->getFilename() . '_cut', $path);
    $newFile = preg_replace('/\.(avi|mp4|mov|wmv|flv|mkv)$/', '.jpg', $newFile);
    $fixedFile = $this->fileSystem->move("{$this->tmpDir}/screenshot.jpeg", $newFile);
    $file = File::create([
      'uri' => $fixedFile,
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    return $file;
  }

  /**
   * Get the correct crop data with the base being 640.
   *
   * @param \Drupal\file\Entity\File $video
   *   The video.
   * @param array $cropData
   *   The crop data.
   *
   * @return array
   *   The corrected crop data.
   */
  public function normalizeCropData(File $video, $cropData) {
    $originalWidth = 640;
    // Get the width and height of the video with FFmpeg.
    $realPath = $this->fileSystem->realpath($video->getFileUri());
    $command = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 \"$realPath\"";
    $result = shell_exec($command);
    [$width, $height] = explode('x', $result);
    $ratio = $width / $originalWidth;
    $newCropData = [];
    foreach ($cropData as $key => $value) {
      $newCropData[$key] = round($value * $ratio);
    }
    return $newCropData;
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
   * Generate the images and audio for OpenAI.
   */
  protected function prepareToExplain(File $file, $video = TRUE, $audio = TRUE) {
    $this->createTempDirectory();
    if ($video) {
      $this->createVideoRasterImages($file);
    }
    if ($audio) {
      $this->createAudioFile($file);
      $this->transcribeAudio();
    }
  }

  /**
   * Helper function to get the image raster from the video.
   */
  protected function createAudioFile(File $file) {
    // Get the video file.
    $video = $file->getFileUri();
    // Get the actual file path on the server.
    $realPath = $this->fileSystem->realpath($video);
    // Let FFMPEG do its magic.
    $command = "ffmpeg -y -nostdin  -i \"$realPath\" -c:a mp3 -b:a 64k {$this->tmpDir}/audio.mp3";
    exec($command, $status);
    if ($status) {
      throw new AiInterpolatorResponseErrorException('Could not generate audio from video.');
    }
    return '';
  }

  /**
   * Transcribe the audio.
   */
  protected function transcribeAudio() {
    // Use Whisper to transcribe and then get the segments.
    $input = [
      'model' => 'whisper-1',
      'file' => fopen($this->tmpDir . '/audio.mp3', 'r'),
      'response_format' => 'json',
    ];
    $segments = $this->openAi->transcribe($input, TRUE);
    // Create a string that we can use as context.
    $text = '';
    foreach ($segments as $segment) {
      $text .= $segment['start'] . ' - ' . $segment['end'] . "\n";
      $text .= $segment['text'] . "\n";
    }
    $this->transcription = $text;
  }

  /**
   * Helper function to get the image raster images from the video.
   */
  protected function createVideoRasterImages(File $file) {
    // Get the video file.
    $video = $file->getFileUri();
    // Get the actual file path on the server.
    $realPath = $this->fileSystem->realpath($video);
    // Let FFMPEG do its magic.
    $command = "ffmpeg -y -nostdin  -i \"$realPath\" -vf \"select='gt(scene,0.1)',scale=640:-1,drawtext=fontsize=45:fontcolor=yellow:box=1:boxcolor=black:x=(W-tw)/2:y=H-th-10:text='%{pts\:hms}'\" -vsync vfr {$this->tmpDir}/output_frame_%04d.jpg";
    exec($command, $status);
    // If it failed, give up.
    if ($status) {
      throw new AiInterpolatorResponseErrorException('Could not create video thumbs.');
    }
    $rasterCommand = "ffmpeg -i {$this->tmpDir}/output_frame_%04d.jpg -filter_complex \"scale=640:-1,tile=3x3:margin=10:padding=4:color=white\" {$this->tmpDir}/raster-%04d.jpeg";
    exec($rasterCommand, $status);
    // If it failed, give up.
    if ($status) {
      throw new AiInterpolatorResponseErrorException('Could not create video raster.');
    }
    $images = glob($this->tmpDir . 'raster-*.jpeg');
    foreach ($images as $uri) {
      $this->images[] = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($uri));
    }
  }

  /**
   * Helper function to generate a temp directory.
   */
  protected function createTempDirectory() {
    $this->tmpDir = $this->fileSystem->getTempDirectory() . '/' . mt_rand(10000, 99999) . '/';
    if (!file_exists($this->tmpDir)) {
      $this->fileSystem->mkdir($this->tmpDir);
    }
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  public function getEntityTokenType($entityTypeId) {
    switch ($entityTypeId) {
      case 'taxonomy_term':
        return 'term';
    }
    return $entityTypeId;
  }

  /**
   * Render a tokenized prompt.
   *
   * @var string $prompt
   *   The prompt.
   * @var \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The rendered prompt.
   */
  public function renderTokenPrompt($prompt, ContentEntityInterface $entity) {
    // Get variables.
    return $this->token->replace($prompt, [
      $this->getEntityTokenType($entity->getEntityTypeId()) => $entity,
      'user' => $this->currentUser,
    ]);
  }

}
