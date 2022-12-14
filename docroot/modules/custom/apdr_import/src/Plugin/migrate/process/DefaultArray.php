<?php

namespace Drupal\apdr_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "default_array"
 * )
 *
 * To do custom value transformations use the following:
 *
 * @code
 * field_text:
 *   plugin: default_array
 *   source: text
 * @endcode
 *
 */
class DefaultArray extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-primary'],
      ]
    ];
    return $options;
  }
}
