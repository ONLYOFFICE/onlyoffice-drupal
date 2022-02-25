<?php

namespace Drupal\onlyoffice_connector\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "onlyoffice_connector_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("ONLYOFFICE Connector")
 * )
 */
class ExampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
