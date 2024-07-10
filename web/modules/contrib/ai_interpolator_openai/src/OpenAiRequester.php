<?php

namespace Drupal\ai_interpolator_openai;

use Drupal\ai_interpolator\Exceptions\AiInterpolatorRequestErrorException;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorResponseErrorException;
use Drupal\Core\Field\FieldDefinitionInterface;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;

/**
 * A wrapper around OpenAI.
 */
class OpenAiRequester {

  /**
   * The OpenAI client.
   */
  protected Client $client;

  /**
   * Constructs a new CampaignListBuilder object.
   *
   * @param \OpenAI\Client $client
   *   The OpenAI client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Generate response.
   *
   * @param string $prompt
   *   The prompt.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $interpolatorConfig
   *   The interpolator config.
   * @param array $images
   *   Images to pass on to OpenAI Vision.
   *
   * @return array
   *   The array of values.
   */
  public function generateResponse(string $prompt, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig, $images = []) {
    try {
      $response = $this->openAiChatRequest($prompt, $interpolatorConfig, $images);
    }
    catch (\Exception $e) {
      throw new AiInterpolatorRequestErrorException($e->getMessage());
    }

    if (isset($response['choices'][0]['message']['content'])) {
      $json = json_decode(str_replace("\n", "", trim(str_replace('```json', '', $response['choices'][0]['message']['content']), '```')), TRUE);
      // Sometimes it doesn't become a valid JSON response, but many.
      if (!is_array($json)) {
        $newJson = [];
        foreach (explode("\n", $response['choices'][0]['message']['content']) as $row) {
          if ($row) {
            $parts = json_decode(str_replace("\n", "", $row), TRUE);
            if (is_array($parts)) {
              $newJson = array_merge($newJson, $parts);
            }
          }
        }
        if (!empty($newJson)) {
          $json = $newJson;
        }
      }
      if (isset($json[0]['value'])) {
        $values = [];
        foreach ($json as $val) {
          if (isset($val['value'])) {
            $values[] = $val['value'];
          }
        }
        return $values;
      }
      // Sometimes it sets the wrong key.
      elseif (isset($json[0])) {
        $values = [];
        foreach ($json as $val) {
          if (isset($val[key($val)])) {
            $values[] = $val[key($val)];
          }
          return $values;
        }
      }
      // Sometimes it does not return with values in GPT 3.5.
      elseif (is_array($json) && isset($json[0][0])) {
        $values = [];
        foreach ($json as $vals) {
          foreach ($vals as $val) {
            if (isset($val)) {
              $values[] = $val;
            }
          }
        }
        return $values;
      }
      elseif (isset($json['value'])) {
        return [$json['value']];
      }
      else {
        return [$response['choices'][0]['message']['content']];
      }
    }

    throw new AiInterpolatorResponseErrorException('The response did not follow the wanted structure.');
  }

  /**
   * Get all models.
   *
   * @return array
   *   The key and name of the models.
   */
  public function getModels() {
    try {
      $response = $this->client->models()->list();
    }
    catch (ErrorException $e) {
      return [];
    }

    // Only allow 3.5 or 4 based.
    $models = [];
    foreach ($response->data as $result) {
      // Currently there is no better way to search.
      // This would includ finetuned models based on these.
      if (substr($result->id, 0, 7) == 'gpt-3.5' || substr($result->id, 0, 5) == 'gpt-4' || substr($result->id, 0, 7) == 'sahara:') {
        $models[$result->id] = $result->id;
      }
    }
    return $models;
  }

  /**
   * Generate image.
   *
   * @param array $parameters
   *   The parameters in.
   *
   * @return array
   *   The key and name of the models.
   */
  public function generateImage($parameters) {
    try {
      $response = $this->client->images()->create($parameters);
    }
    catch (ErrorException $e) {
      return [];
    }

    // All urls.
    $values = [];
    foreach ($response->data as $result) {
      // Currently there is no better way to search.
      // This would includ finetuned models based on these.
      if (!empty($result->url)) {
        $values[] = $result->url;
      }
    }
    return $values;
  }

  /**
   * Transcribe audio.
   *
   * @param array $parameters
   *   The parameters in.
   * @param bool $segmented
   *   If the transcription is returned segmented.
   *
   * @return array
   *   The full text or segments.
   */
  public function transcribe($parameters, $segmented = FALSE) {
    // Segmented output needs verbose JSON.
    if ($segmented) {
      $parameters['response_format'] = 'verbose_json';
    }

    try {
      $response = $this->client->audio()->transcribe($parameters);
    }
    catch (ErrorException $e) {
      echo $e->getMessage();

      return [];
    }

    if ($segmented) {
      $segments = [];
      foreach ($response->segments as $segment) {
        $segments[] = [
          'text' => $segment->text,
          'start' => $segment->start,
          'end' => $segment->end,
        ];
      }
      return $segments;
    }
    return [
      'text' => $response->text,
    ];
  }

  /**
   * Moderate a query.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $interpolatorConfig
   *   The interpolator config.
   *
   * @return bool
   *   If the moderation is flagged or not.
   */
  public function hasFlaggedContent($prompt, array $interpolatorConfig) {
    $response = $this->client->moderations()->create([
      'input' => $prompt,
    ]);

    foreach ($response->results as $result) {
      if ($result->flagged) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Generate the OpenAI Chat request.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $interpolatorConfig
   *   The interpolator config.
   * @param array $images
   *   Images to pass on to OpenAI Vision.
   *
   * @return string
   *   The response.
   */
  public function openAiChatRequest($prompt, $interpolatorConfig, $images = []) {
    if (!empty($interpolatorConfig['openai_role'])) {
      $messages[] = [
        'role' => 'system',
        'content' => trim($interpolatorConfig['openai_role']),
      ];
    }
    $content[] = [
      "type" => "text",
      "text" => $prompt,
    ];
    if (!empty($images)) {
      foreach ($images as $image) {
        $content[] = [
          "type" => "image_url",
          "image_url" => [
            "url" => $image,
          ],
        ];
      }
    }
    $messages[] = [
      'role' => 'user',
      'content' => $content,
    ];
    try {
      $response = $this->client->chat()->create([
        'model' => $interpolatorConfig['openai_model'] ?? 'gpt-3.5-turbo',
        'messages' => $messages,
        'max_tokens' => 'gpt-4' ? 4096 : 2048,
      ]);
    }
    catch (ErrorException $e) {
      return $e->getMessage();
    }

    $result = $response->toArray();
    return $result;
  }

}
