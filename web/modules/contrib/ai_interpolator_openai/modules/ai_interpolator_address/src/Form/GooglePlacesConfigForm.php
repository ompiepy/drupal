<?php

namespace Drupal\ai_interpolator_address\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Google Places API access.
 */
class GooglePlacesConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_interpolator_address.google_places_settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_interpolator_address_google_places_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Google Places API Key'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
