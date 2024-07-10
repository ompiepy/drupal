<?php

namespace Drupal\ai_interpolator_address;

use Drupal\ai_interpolator_address\Form\GooglePlacesConfigForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;

/**
 * Google Places API creator.
 */
class GooglePlacesApi {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  private $apiKey;

  /**
   * The base path.
   *
   * @var string
   */
  private $basePath = 'https://maps.googleapis.com/maps/api/place/';

  /**
   * Constructs a new Google Places object.
   *
   * @param \GuzzleHttp\Client $client
   *   Http client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(Client $client, ConfigFactoryInterface $configFactory) {
    $this->client = $client;
    $this->apiKey = $configFactory->get(GooglePlacesConfigForm::CONFIG_NAME)->get('api_key') ?? '';
  }

  /**
   * Gets an places object.
   *
   * @param string $name
   *   The name to search for.
   * @param string $address
   *   The address to search for.
   *
   * @return array
   *   All Google info.
   */
  public function getPlaceInfo($name, $address) {
    if (!$this->apiKey) {
      return [];
    }
    $headers['accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';
    $qs['input'] = $name . ', ' . $address;
    $qs['inputtype'] = 'textquery';
    $candidates = json_decode($this->makeRequest('findplacefromtext/json', $qs, 'GET', NULL, $headers)->getBody(), TRUE);
    if (!empty($candidates['candidates'][0]['place_id'])) {
      $qs = [
        'place_id' => $candidates['candidates'][0]['place_id'],
      ];
      $response = json_decode($this->makeRequest('details/json', $qs, 'GET', NULL, $headers)->getBody(), TRUE);
      return $response;
    }
    return [];
  }

  /**
   * Get all voices.
   *
   * @return string
   *   Photo binary.
   */
  public function getPhoto($id) {
    if (!$this->apiKey) {
      return [];
    }
    $qs['maxwidth'] = 1920;
    $qs['photo_reference'] = $id;
    $res = $this->makeRequest('photo', $qs);
    return $res->getBody();
  }

  /**
   * Make google call.
   *
   * @param string $path
   *   The path.
   * @param array $query_string
   *   The query string.
   * @param string $method
   *   The method.
   * @param string $body
   *   Data to attach if POST/PUT/PATCH.
   * @param array $headers
   *   Extra headers.
   *
   * @return \Guzzle\Http\Message\Response
   *   The return response.
   */
  protected function makeRequest($path, array $query_string = [], $method = 'GET', $body = '', array $headers = []) {
    // We can't wait forever.
    $options['connect_timeout'] = 10;
    $options['read_timeout'] = 10;
    // Don't let Guzzle die, just forward body and status.
    $options['http_errors'] = FALSE;
    // Headers.
    $options['headers'] = $headers;
    // API key.
    $query_string['key'] = $this->apiKey;

    if ($body) {
      $options['body'] = $body;
    }

    $new_url = $this->basePath . $path;
    $new_url .= count($query_string) ? '?' . http_build_query($query_string) : '';

    $res = $this->client->request($method, $new_url, $options);

    return $res;
  }

}
