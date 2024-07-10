<?php

namespace Drupal\try_eca\Controller;

use Drupal\Core\Controller\ControllerBase;

class TryECATestController extends ControllerBase {

  public function test() {
    return [
      '#markup' => $this->t('Try ECA module is working!'),
    ];
  }

}
