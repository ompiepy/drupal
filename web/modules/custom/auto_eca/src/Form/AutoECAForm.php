<?php

namespace Drupal\auto_eca\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AutoECAForm extends FormBase {

  protected $messenger;

  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  public function getFormId() {
    return 'auto_eca_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Enter the prompt to create an ECA rule.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create ECA Rule'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $prompt = $form_state->getValue('prompt');

    // Parse the prompt and create the ECA rule.
    if (strpos($prompt, 'When an article is made') !== FALSE) {
      $this->createECA();
      $this->messenger->addMessage($this->t('ECA rule created successfully.'));
    } else {
      $this->messenger->addMessage($this->t('Invalid prompt.'), 'error');
    }
  }

  protected function createECA() {

    $rule = \Drupal::entityTypeManager()->getStorage('eca_rule')->create([
      'id' => 'notify_user_on_article_creation',
      'label' => 'Notify user on article creation',
      'events' => [
        'node_insert' => [
          'node_type' => 'article',
        ],
      ],
      'conditions' => [],
      'actions' => [
        [
          'plugin' => 'display_message',
          'configuration' => [
            'message' => 'An article has been created.',
            'type' => 'status',
          ],
        ],
      ],
    ]);
    $rule->save();
  }
  
}
