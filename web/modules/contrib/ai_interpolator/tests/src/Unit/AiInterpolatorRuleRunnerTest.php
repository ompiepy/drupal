<?php

namespace Drupal\Tests\ai_interpolator\Unit;

use Drupal\ai_interpolator\AiFieldRules;
use Drupal\ai_interpolator\AiInterpolatorRuleRunner;
use Drupal\ai_interpolator\Annotation\AiInterpolatorFieldRule;
use Drupal\ai_interpolator\Exceptions\AiInterpolatorRuleNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_interpolator\AiInterpolatorRuleRunner
 * @group ai_interpolator
 */
class AiInterpolatorRuleRunnerTest extends UnitTestCase {

  /**
   * The status field under test.
   */
  protected AiInterpolatorRuleRunner $statusField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $entityTypeManager = $this->createMock(EntityTypeManager::class);
    $aiFieldRules = $this->createMock(AiFieldRules::class);
    $aiFieldRules
      ->method('findRule')
      ->will($this->returnCallback(
        function ($ruleName) {
          if ($ruleName == 'string') {
            $rule = $this->createMock(AiInterpolatorFieldRule::class);
            $rule
              ->method('generateTokens')
              ->willReturn(
                [
                  'token1' => 'hello',
                ]
              );
            return $rule;
          }
          return NULL;
        }
      )
    );

    $this->ruleRunner = new AiInterpolatorRuleRunner($entityTypeManager, $aiFieldRules);
  }

  /**
   * Test a rule that can't be found.
   */
  public function testFaultyRule(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition
      ->method('getType')
      ->willReturn('none_existing');
    $interpolatorConfig = [
      'rule' => 'none_exisiting',
    ];
    $this->expectException(AiInterpolatorRuleNotFoundException::class);
    $this->expectExceptionMessage('The rule could not be found: none_existing');
    $this->ruleRunner->generateResponse($contentEntity, $fieldDefinition, $interpolatorConfig);
  }

}
