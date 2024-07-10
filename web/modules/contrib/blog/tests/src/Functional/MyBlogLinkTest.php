<?php

namespace Drupal\Tests\blog\Functional;

/**
 * Link "My blog" and "View recent blog entries" test for blog module.
 *
 * @group blog
 */
class MyBlogLinkTest extends BlogTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'blog',
    'field_ui',
  ];

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create regular user.
    $this->regularUser = $this->drupalCreateUser(['create article content', 'administer user display']);
    // Add account_menu block.
    $this->placeBlock('system_menu_block:account', ['region' => 'content']);
  }

  /**
   * Test "My blog" link with regular user.
   */
  public function testMyBlogLinkWithRegularUser() {
    $this->drupalLogin($this->regularUser);
    $this->assertSession()->linkExists('My blog');
    $this->assertSession()->linkByHrefExists('/blog/' . $this->regularUser->id());
  }

  /**
   * Test "My blog" link with anonymous user.
   */
  public function testMyBlogLinkWithAnonUser() {
    $this->assertSession()->linkNotExists('My blog');
  }

  /**
   * Test "Personal blog link" entry.
   */
  public function testPersonalBlogLinkWithManageDisplayPage() {
    $this->drupalLogin($this->regularUser);
    // Add "Personal blog link" psuedo-field.
    $this->drupalGet('admin/config/people/accounts/display');
    $this->assertSession()->pageTextContains('Personal blog link');
    $edit = [
      'fields[blog__personal_blog_link][region]' => 'content',
      'display_modes_custom[compact]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalLogout();
    // Test "Personal blog link" on user profile page.
    $this->drupalLogin($this->blogger1);
    $this->drupalGet('user/' . $this->blogger1->id());
    $this->assertSession()->linkExists('View recent blog entries');
    $this->assertSession()->linkByHrefExists('blog/' . $this->blogger1->id());
    // Test "Personal blog link" on content.
    $node = array_shift($this->blogNodes1);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->linkNotExists('View recent blog entries');
  }

}
