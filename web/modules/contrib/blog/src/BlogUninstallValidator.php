<?php

namespace Drupal\blog;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prevents blog module from being uninstalled whilst any blog nodes exist.
 */
class BlogUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new validator.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    if ($module === 'blog' && blog_post_counter() != 0) {
      $reasons[] = $this->t('To uninstall Blog module, first delete all <em>Blog Post</em> content.');
    }
    return $reasons;
  }

}
