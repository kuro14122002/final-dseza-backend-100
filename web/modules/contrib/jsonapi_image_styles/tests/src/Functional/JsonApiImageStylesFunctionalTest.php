<?php

namespace Drupal\Tests\jsonapi_image_styles\Functional;

use Drupal\Component\Serialization\Json;

/**
 * The test class for the main functionality.
 *
 * @group jsonapi_image_styles
 */
class JsonApiImageStylesFunctionalTest extends JsonApiImageStylesFunctionalTestBase {

  /**
   * Tests that only the configured image styles are on the JSON:API response.
   */
  public function testImageStylesOnJsonApiResponse() {
    $this->createDefaultContent(1, 1, TRUE, TRUE, static::IS_NOT_MULTILINGUAL);
    $response = $this->drupalGet('/jsonapi/node/article', [
      'query' => ['include' => 'field_image'],
    ]);
    $output = Json::decode($response);
    $this->assertArrayHasKey('image_style_uri', $output['included'][0]['attributes']);

    // Assert only the two configured image styles (large, thumbnail).
    $styles = $output['included'][0]['attributes']['image_style_uri'];
    $this->assertCount(2, $styles);
    $this->assertArrayHasKey('large', $styles);
    $this->assertArrayHasKey('thumbnail', $styles);
    $this->assertArrayNotHasKey('wide', $styles);
    $this->assertArrayNotHasKey('medium', $styles);
  }

}
