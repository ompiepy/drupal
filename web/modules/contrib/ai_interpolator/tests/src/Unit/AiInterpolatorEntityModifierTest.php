<?php

namespace Drupal\Tests\ai_interpolator\Unit;

use Drupal\ai_interpolator\AiFieldRules;
use Drupal\ai_interpolator\AiInterpolatorEntityModifier;
use Drupal\ai_interpolator\Plugin\AiInterpolatorProcess\BatchProcessing;
use Drupal\ai_interpolator\Plugin\AiInterpolatorProcess\DirectSaveProcessing;
use Drupal\ai_interpolator\Plugin\AiInterpolatorProcess\QueueWorkerProcessor;
use Drupal\ai_interpolator\PluginInterfaces\AiInterpolatorFieldRuleInterface;
use Drupal\ai_interpolator\PluginManager\AiInterpolatorFieldProcessManager;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_interpolator\AiInterpolatorEntityModifier
 * @group ai_interpolator
 */
class AiInterpolatorEntityModifierTest extends UnitTestCase {

  /**
   * The Entity Modifier under test.
   */
  protected AiInterpolatorEntityModifier $entityModifier;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Drupal dependency hell :(.
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager
      ->method('getFieldDefinitions')
      ->will($this->returnCallback(
        function ($entityType, $bundle) {
          if ($entityType == 'entity_with_configs' && $bundle == 'bundle_with_configs_not_enabled') {
            $fieldConfigDisabled = $this->createMock(FieldConfigInterface::class);
            $fieldConfigDisabled
              ->method('getThirdPartySetting')
              ->willReturn(FALSE);
            $fieldDefinitionDisabled = $this->createMock(FieldDefinitionInterface::class);
            $fieldDefinitionDisabled
              ->method('getConfig')
              ->willReturn($fieldConfigDisabled);
            return [$fieldDefinitionDisabled];
          }
          elseif ($entityType == 'entity_with_configs' && $bundle == 'bundle_with_configs') {
            $fieldConfigEnabled = $this->createMock(FieldConfigInterface::class);
            $fieldConfigEnabled
              ->method('getThirdPartySetting')
              ->willReturn(TRUE);
            $fieldConfigEnabled
              ->method('getName')
              ->willReturn('groot');
            $fieldConfigEnabled
              ->method('getThirdPartySettings')
              ->willReturn([
                'test_not_catched' => 'hello',
                'interpolator_i_am_groot' => 'I am Groot!',
                'interpolator_fancy' => 'name',
                'interpolator_base_field' => 'description',
                'interpolator_edit_mode' => TRUE,
                'interpolator_worker_type' => 'queue',
              ]);
            $fieldDefinitionEnabled = $this->createMock(FieldDefinitionInterface::class);
            $fieldDefinitionEnabled
              ->method('getConfig')
              ->willReturn($fieldConfigEnabled);
            return [$fieldDefinitionEnabled];
          }
          return [];
        }
      )
    );

    $processManager = $this->createMock(AiInterpolatorFieldProcessManager::class);
    $processManager
      ->method('getDefinitions')
      ->willReturn([
        [
          'id' => 'queue',
          'title' => 'Queue',
          'description' => 'Queue',
        ],
        [
          'id' => 'direct',
          'title' => 'Direct',
          'description' => 'Direct',
        ],
        [
          'id' => 'batch',
          'title' => 'Batch',
          'description' => 'Batch',
        ],
      ]);

    $processManager
      ->method('getDefinition')
      ->will($this->returnCallback(
        function ($id) {
          if ($id == 'queue') {
            return [
              'id' => 'queue',
              'title' => 'Queue',
              'description' => 'Queue',
            ];
          }
          elseif ($id == 'direct') {
            return [
              'id' => 'direct',
              'title' => 'Direct',
              'description' => 'Direct',
            ];
          }
          elseif ($id == 'batch') {
            return [
              'id' => 'batch',
              'title' => 'Batch',
              'description' => 'Batch',
            ];
          }
          return NULL;
        }
      )
    );

    $processManager
      ->method('createInstance')
      ->willReturnCallback(
        function ($id) {
          if ($id == 'queue') {
            return $this->createMock(QueueWorkerProcessor::class);
          }
          elseif ($id == 'direct') {
            return $this->createMock(DirectSaveProcessing::class);
          }
          elseif ($id == 'batch') {
            return $this->createMock(BatchProcessing::class);
          }
          return NULL;
        }
      );

    $aiFieldRules = $this->createMock(AiFieldRules::class);
    $aiFieldRules
      ->method('findRuleCandidates')
      ->willReturn([
        'i_am_groot' => $this->createMock(AiInterpolatorFieldRuleInterface::class),
        'fancy' => $this->createMock(AiInterpolatorFieldRuleInterface::class),
      ]);

    $this->entityModifier = new AiInterpolatorEntityModifier($entityFieldManager, $processManager, $aiFieldRules);
  }

  /**
   * Test that config entities just fails.
   */
  public function testConfigEntity(): void {
    $configEntity = $this->createMock(ConfigEntityInterface::class);
    $this->assertFalse($this->entityModifier->saveEntity($configEntity));
  }

  /**
   * Test that loads entity without configs.
   */
  public function testEntityWithoutConfigs(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_without_configs');

    $this->assertEmpty($this->entityModifier->entityHasConfig($contentEntity));
  }

  /**
   * Test that loads entity with config and bundle without configs.
   */
  public function testEntityWithConfigsWithoutBundle(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_without_configs');

    $this->assertEmpty($this->entityModifier->entityHasConfig($contentEntity));
  }

  /**
   * Test that loads entity without enabled settings.
   */
  public function testEntityWithConfigsWithBundleDisabled(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_with_configs_not_enabled');

    $this->assertEmpty($this->entityModifier->entityHasConfig($contentEntity));
  }

  /**
   * Test that loads entity all working.
   */
  public function testEntityWithConfigsWithBundleEnabled(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_with_configs');

    $returnArray = $this->entityModifier->entityHasConfig($contentEntity);
    $this->assertArrayHasKey('groot', $returnArray);
    $this->assertArrayHasKey('interpolatorConfig', $returnArray['groot']);
    $this->assertArrayHasKey('fieldDefinition', $returnArray['groot']);
    $this->assertInstanceOf(FieldDefinitionInterface::class, $returnArray['groot']['fieldDefinition']);
    $this->assertArrayHasKey('i_am_groot', $returnArray['groot']['interpolatorConfig']);
    $this->assertArrayHasKey('fancy', $returnArray['groot']['interpolatorConfig']);
    $this->assertArrayNotHasKey('test_not_catched', $returnArray['groot']['interpolatorConfig']);
  }

  /**
   * Test that loads entity not working.
   */
  public function testSavingEntityWithoutConfig(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_with_configs_not_enabled');

    $this->assertFalse($this->entityModifier->saveEntity($contentEntity));
  }

  /**
   * Test that loads entity but empty base field.
   */
  public function testSavingEntityWithConfigWithoutBaseFieldValue(): void {
    $fieldItem = $this->createMock(FieldItemListInterface::class);
    $fieldItem
      ->method('getValue')
      ->willReturn([]);
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_with_configs');
    $contentEntity
      ->method('get')
      ->willReturn($fieldItem);
    $contentEntity->description = new \stdClass();
    $contentEntity->description->value = '';
    $contentEntity->groot = new \stdClass();
    $contentEntity->groot->value = 'test';

    // It will still be true.
    $this->assertTrue($this->entityModifier->saveEntity($contentEntity));
  }

  /**
   * Test that loads entity but empty base field.
   */
  public function testSavingEntityWithConfigWithBaseFieldValue(): void {
    $fieldItem = $this->createMock(FieldItemListInterface::class);
    $fieldItem
      ->method('getValue')
      ->willReturn([]);
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $contentEntity
      ->method('getEntityTypeId')
      ->willReturn('entity_with_configs');
    $contentEntity
      ->method('bundle')
      ->willReturn('bundle_with_configs');
    $contentEntity
      ->method('get')
      ->willReturn($fieldItem);
    $contentEntity->description = new \stdClass();
    $contentEntity->description->value = 'test';
    $contentEntity->groot = new \stdClass();
    $contentEntity->groot->value = 'test';

    // It will still be true.
    $this->assertTrue($this->entityModifier->saveEntity($contentEntity));
  }

}
