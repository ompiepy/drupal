<?php

namespace Drupal\Tests\ai_interpolator\Unit;

use Drupal\ai_interpolator\AiPromptHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_interpolator\AiPromptHelper
 * @group ai_interpolator
 */
class AiPromptHelperTest extends UnitTestCase {

  /**
   * The status field under test.
   */
  protected AiPromptHelper $promptHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $twigEnvironment = $this->createMock(TwigEnvironment::class);

    $accountProxy = $this->createMock(AccountProxy::class);
    $token = $this->createMock(Token::class);
    $token
      ->method('replace')
      ->willReturn('testing to complete');

    $this->promptHelper = new AiPromptHelper($twigEnvironment, $accountProxy, $token);
  }

  /**
   * Test token rendering.
   */
  public function testTokenRendering(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $return = $this->promptHelper->renderTokenPrompt('testing to complete', $contentEntity);
    $this->assertEquals('testing to complete', $return);
  }

}
