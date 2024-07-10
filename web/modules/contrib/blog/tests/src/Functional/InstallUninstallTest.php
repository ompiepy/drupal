<?php

namespace Drupal\Tests\blog\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test for blog module install and uninstall.
 *
 * @group blog
 */
class InstallUninstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'blog',
  ];

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  private $moduleInstaller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleInstaller = \Drupal::service('module_installer');
  }

  /**
   * Tests module re-install.
   */
  public function testReinstall() {
    $problems = $this->moduleInstaller->validateUninstall(['blog']);
    $this::assertEmpty($problems);
    $this->moduleInstaller->uninstall(['blog']);
    // Blog module reinstall.
    $this->moduleInstaller->install(['blog']);
    $problems = $this->moduleInstaller->validateUninstall(['blog']);
    $this::assertEmpty($problems);
    $this->moduleInstaller->uninstall(['blog']);
  }

  /**
   * Tests module uninstall.
   */
  public function testUninstall() {
    // Create blogger user.
    $blogger = $this->drupalCreateUser([
      'create blog_post content',
    ]);
    $problems = $this->moduleInstaller->validateUninstall(['blog']);
    $this::assertEmpty($problems);
    // Create blog posts.
    $blog_node = $this->createNode([
      'type' => 'blog_post',
      'title' => $this->randomMachineName(32),
      'uid' => $blogger->id(),
    ]);
    // Validate blog module uninstall.
    $problems = $this->moduleInstaller->validateUninstall(['blog']);
    $this::assertNotEmpty($problems);
    // Delete blog posts.
    $blog_node->delete();
    // Uninstall blog module.
    $problems = $this->moduleInstaller->validateUninstall(['blog']);
    $this::assertEmpty($problems);
  }
}
