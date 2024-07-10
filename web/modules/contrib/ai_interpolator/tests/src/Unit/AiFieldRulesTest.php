<?php

namespace Drupal\Tests\ai_interpolator\Unit;

use Drupal\ai_interpolator\AiFieldRules;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldRuleManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_interpolator\AiFieldRules
 * @group ai_interpolator
 */
class AiFieldRulesTest extends UnitTestCase {

  /**
   * The Ai Field Rules under test.
   */
  protected AiFieldRules $fieldRules;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $fieldRuleManager = $this->createMock(AiInterpolatorFieldRuleManager::class);
    $fieldRuleManager
      ->method('getDefinitions')
      ->willReturn([
        [
          'id' => 'definition_1',
          'field_rule' => 'boolean',
          'target' => NULL,
        ],
        [
          'id' => 'definition_2',
          'field_rule' => 'boolean',
          'target' => NULL,
        ],
        [
          'id' => 'definition_3_with_target',
          'field_rule' => 'entity_reference',
          'target' => 'taxonomy_term',
        ],
      ]);
    $fieldRuleManager
      ->method('createInstance')
      ->will($this->returnCallback(
        function ($argument) {
          $fieldRule = $this->createMock(AiInterpolatorFieldRuleInterface::class);
          $fieldRule
            ->method('ruleIsAllowed')
            ->willReturn(TRUE);
          switch ($argument) {
            case 'definition_1':
              return $fieldRule;

            case 'definition_2':
              return $fieldRule;

            case 'definition_3_with_target':
              return $fieldRule;
          }
          return NULL;
        }
      )
    );

    $this->fieldRules = new AiFieldrules($fieldRuleManager);
  }

  /**
   * Test that the find rules returns mockup object.
   */
  public function testFindingRule(): void {
    $this->assertNotNull($this->fieldRules->findRule('definition_1'));
  }

  /**
   * Test that the find rules returns nothing.
   */
  public function testNotFindingRule(): void {
    $this->assertNull($this->fieldRules->findRule('not_found'));
  }

  /**
   * Test that the find rules returns mockup object.
   */
  public function testFindingCandidates(): void {
    $settingsInterface = $this->createMock(FieldStorageDefinitionInterface::class);
    $settingsInterface
      ->method('getSettings')
      ->willReturn([
        'target_type' => '',
      ]);
    $fieldInterface = $this->createMock(FieldDefinitionInterface::class);
    $fieldInterface
      ->method('getFieldStorageDefinition')
      ->willReturn($settingsInterface);
    $fieldInterface
      ->method('getType')
      ->willReturn('boolean');
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $this->assertArrayHasKey('definition_1', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayHasKey('definition_2', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayNotHasKey('definition_3_with_target', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
  }

  /**
   * Test that the find rules returns nothing.
   */
  public function testNotFindingCandidates(): void {
    $settingsInterface = $this->createMock(FieldStorageDefinitionInterface::class);
    $settingsInterface
      ->method('getSettings')
      ->willReturn([
        'target_type' => '',
      ]);
    $fieldInterface = $this->createMock(FieldDefinitionInterface::class);
    $fieldInterface
      ->method('getFieldStorageDefinition')
      ->willReturn($settingsInterface);
    $fieldInterface
      ->method('getType')
      ->willReturn('string');
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $this->assertArrayNotHasKey('definition_1', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayNotHasKey('definition_2', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayNotHasKey('definition_3_with_target', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
  }

  /**
   * Test that the find rules returns mockup object for targets.
   */
  public function testFindingTargetCandidates(): void {
    $settingsInterface = $this->createMock(FieldStorageDefinitionInterface::class);
    $settingsInterface
      ->method('getSettings')
      ->willReturn([
        'target_type' => 'taxonomy_term',
      ]);
    $fieldInterface = $this->createMock(FieldDefinitionInterface::class);
    $fieldInterface
      ->method('getFieldStorageDefinition')
      ->willReturn($settingsInterface);
    $fieldInterface
      ->method('getType')
      ->willReturn('entity_reference');
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $this->assertArrayNotHasKey('definition_1', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayNotHasKey('definition_2', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
    $this->assertArrayHasKey('definition_3_with_target', $this->fieldRules->findRuleCandidates($contentEntity, $fieldInterface));
  }

}
