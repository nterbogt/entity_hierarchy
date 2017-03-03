<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\system\Controller\EntityAutocompleteController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests autocomplete handler.
 *
 * @group entity_hierarchy.
 */
class AutocompleteHandlerTest extends EntityHierarchyKernelTestBase {

  /**
   * Autocomplete settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * Tests autocomplete handler.
   */
  public function testAutoCompleteHandler() {
    $child = $this->createTestEntity($this->parent->id(), 'Child');
    $grandchild = $this->createTestEntity($child->id(), 'Grandchild');
    $great_grandchild = $this->createTestEntity($grandchild->id(), 'Great Grandchild');
    $results = $this->getAutocompleteResult('Great');
    $this->assertCount(1, $results);
    $result = reset($results);
    $this->assertEquals([
      'value' => sprintf('Great Grandchild (%s)', $great_grandchild->id()),
      'label' => sprintf('Great Grandchild (%s ❭ Child ❭ Grandchild)', $this->parent->id()),
    ], $result);
  }

  /**
   * Returns the result of an Entity reference autocomplete request.
   *
   * @param string $input
   *   The label of the entity to query by.
   *
   * @return mixed
   *   The JSON value encoded in its appropriate PHP type.
   */
  protected function getAutocompleteResult($input) {
    $request = Request::create('entity_reference_autocomplete/' . $this->entityType . '/entity_hierarchy');
    $request->query->set('q', $input);

    $selection_settings_key = Crypt::hmacBase64(serialize($this->settings) . $this->entityType . 'default', Settings::getHashSalt());
    \Drupal::keyValue('entity_autocomplete')->set($selection_settings_key, $this->settings);

    $entity_reference_controller = EntityAutocompleteController::create($this->container);
    $result = $entity_reference_controller->handleAutocomplete($request, $this->entityType, 'default', $selection_settings_key)->getContent();

    return Json::decode($result);
  }

}
