<?php

namespace Drupal\Tests\ai_interpolator\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Testing to check that setup config for a field exists.
 *
 * @group my_module
 */
class FieldConfigTest extends BrowserTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'text',
    'field',
    'field_ui',
    'filter',
    'user',
    'system',
    'ai_interpolator',
  ];

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * Tests the field config form.
   */
  public function testForm() {
    // Create the user with the appropriate permission.
    $admin_user = $this->drupalCreateUser([
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
    ]);

    // Create a content type with necessary fields.
    $this->drupalCreateContentType([
      'type' => 'mockup_article',
      'name' => 'Mockup Article',
    ]);

    // Create the field on the content type.
    $this->createCustomBaseField();

    // Login as our account.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/types/manage/mockup_article/fields/node.mockup_article.field_mockup_base_field');
    /** @var \Drupal\Tests\WebAssert */
    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue(TRUE);
  }

  /**
   * Create the custom field on the content type.
   */
  protected function createCustomBaseField() {
    $field_storage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => 'field_mockup_base_field',
      'entity_type' => 'node',
      'type' => 'text_long',
    ]);
    $field_storage->save();

    $field_instance = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => 'field_mockup_base_field',
      'entity_type' => 'node',
      'bundle' => 'mockup_article',
      'label' => 'Mockup Base Field',
    ]);
    $field_instance->save();
  }

}
