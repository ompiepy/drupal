<?php

namespace Drupal\auto_eca\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AutoECAController extends ControllerBase {

  protected $formBuilder;

  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  public function createECAForm() {
    $form = $this->formBuilder->getForm('Drupal\auto_eca\Form\AutoECAForm');
    return $form;
  }
}
