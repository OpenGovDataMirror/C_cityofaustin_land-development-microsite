<?php

namespace Drupal\apdr_import\Component\Utility;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate_drupal\MigrationConfigurationTrait;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\Core\Database\Database;

class APDRDrushRunner {

  use MigrationConfigurationTrait;
  use StringTranslationTrait;

  /**
   * The list of migrations to run and their configuration.
   *
   * @var \Drupal\migrate\Plugin\Migration[]
   */
  protected $migrationList;

  /**
   * MigrateMessage instance to display messages during the migration process.
   *
   * @var \Drupal\migrate_upgrade\DrushLogMigrateMessage
   */
  protected static $messages;

  /**
   * The Drupal version being imported.
   *
   * @var string
   */
  protected $version;

  /**
   * The state key used to store database configuration.
   *
   * @var string
   */
  protected $databaseStateKey;

  /**
   * List of D6 node migration IDs we've seen.
   *
   * @var array
   */
  protected $nodeMigrations = [];

  /**
   * List of process plugin IDs used to lookup migrations.
   *
   * @var array
   */
  protected $migrationLookupPluginIds = [
    'migration',
    'migration_lookup',
  ];

  /**
   * From the provided source information, instantiate the appropriate migrations
   * in the active configuration.
   *
   * @throws \Exception
   */
  public function configure() {
    
  }

  /**
   * Export the configured migration plugins as configuration entities.
   */
  public function export() {
    $db_info = \Drupal::state()->get($this->databaseStateKey);

    // Create a group to hold the database configuration.

    foreach ($this->migrationList as $migration_id => $migration) {
      drush_print(dt('Exporting @migration as @new_migration',
        ['@migration' => $migration_id, '@new_migration' => $this->modifyId($migration_id)]));
      $entity_array['id'] = $migration_id;
      $entity_array['class'] = $migration->get('class');
      $entity_array['cck_plugin_method'] = $migration->get('cck_plugin_method');
      $entity_array['field_plugin_method'] = $migration->get('field_plugin_method');
      $entity_array['migration_group'] = $this->databaseStateKey;
      $entity_array['migration_tags'] = $migration->get('migration_tags');
      $entity_array['label'] = $migration->get('label');
      $entity_array['source'] = $migration->getSourceConfiguration();
      $entity_array['destination'] = $migration->getDestinationConfiguration();
      $entity_array['process'] = $migration->get('process');
      $entity_array['migration_dependencies'] = $migration->getMigrationDependencies();
      $migration_entity = Migration::create($this->substituteIds($entity_array));
      $migration_entity->save();
    }
  }

  /**
   * Rewrite any migration plugin IDs so they won't conflict with the core
   * IDs.
   *
   * @param $entity_array
   *   A configuration array for a migration.
   *
   * @return array
   *   The migration configuration array modified with new IDs.
   */
  protected function substituteIds($entity_array) {
    $entity_array['id'] = $this->modifyId($entity_array['id']);
    foreach ($entity_array['migration_dependencies'] as $type => $dependencies) {
      foreach ($dependencies as $key => $dependency) {
        $entity_array['migration_dependencies'][$type][$key] = $this->modifyId($dependency);
      }
    }
    $this->substituteMigrationIds($entity_array['process']);
    return $entity_array;
  }

  /**
   * Recursively substitute IDs for migration plugins.
   *
   * @param mixed $process
   */
  protected function substituteMigrationIds(&$process) {
    if (is_array($process)) {
      // We found a migration plugin, change the ID.
      if (isset($process['plugin']) && in_array($process['plugin'], $this->migrationLookupPluginIds)) {
        if (is_array($process['migration'])) {
          $new_migration = [];
          foreach ($process['migration'] as $migration) {
            $new_migration[] = $this->modifyId($migration);
          }
          $process['migration'] = $new_migration;
        }
        else {
          $process['migration'] = $this->modifyId($process['migration']);
        }
      }
      else {
        // Recurse on each array member.
        foreach ($process as &$subprocess) {
          $this->substituteMigrationIds($subprocess);
        }
      }
    }
  }

  /**
   * @param $id
   *   The original core plugin ID.
   *
   * @return string
   *   The ID modified to serve as a configuration entity ID.
   */
  protected function modifyId($id) {
    return drush_get_option('migration-prefix', 'upgrade_') . str_replace(':', '_', $id);
  }

  /**
   * Display any messages being logged to the ID map.
   *
   * @param \Drupal\migrate\Event\MigrateIdMapMessageEvent $event
   *   The message event.
   */
  public static function onIdMapMessage(MigrateIdMapMessageEvent $event) {
    if ($event->getLevel() == MigrationInterface::MESSAGE_NOTICE ||
        $event->getLevel() == MigrationInterface::MESSAGE_INFORMATIONAL) {
      $type = 'status';
    }
    else {
      $type = 'error';
    }
    $source_id_string = implode(',', $event->getSourceIdValues());
    $message = t('Source ID @source_id: @message',
      ['@source_id' => $source_id_string, '@message' => $event->getMessage()]);
    static::$messages->display($message, $type);
  }

}