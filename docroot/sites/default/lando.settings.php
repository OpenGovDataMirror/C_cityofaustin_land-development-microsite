<?php

/**
 * Lando specific settings.
 */
$databases['default']['default'] = array(
  'database' => 'drupal8',
  'username' => 'drupal8',
  'password' => 'drupal8',
  'host' => 'database',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
);
$config_directories = array(
  CONFIG_ACTIVE_DIRECTORY => '../config/sync',
  CONFIG_SYNC_DIRECTORY => '../config/sync',
);
// Disable performance settings.
$config['system.performance']['cache']['page']['max_age'] = 0;
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
// Enable development settings.
$settings['hash_salt'] = 'UngqEOh6UXxNjFenILVJAsoA89SxuPBOTP5drDe3wQE';
$settings['container_yamls'][] = __DIR__ . '/lando.services.yml';
$settings['trusted_host_patterns'] = array(
  '^ems\.lndo\.site$',
  '^localhost$',
);
// Reduce the kint levels to avoid out of memory errors.
if (file_exists(DRUPAL_ROOT . '/modules/contrib/devel/kint/kint/Kint.class.php')) {
  require_once DRUPAL_ROOT . '/modules/contrib/devel/kint/kint/Kint.class.php';
  Kint::$maxLevels = 4;
}

// Run the migration import for events on cron
$config['migrate_scheduler']['migrations'] = [
  'import_events' => [
    'time' => 3600,  # To be executed after every 1 hour.
    'update' => TRUE  # To be executed with the --update flag.
  ]
];

// Redis connection
#$settings['redis.connection']['interface'] = 'Predis';
#$settings['redis.connection']['host']      = 'redis.daurbs.0001.usw2.cache.amazonaws.com';  // Your Redis instance hostname.
#$settings['cache_prefix'] = 'zilleemdev_';
