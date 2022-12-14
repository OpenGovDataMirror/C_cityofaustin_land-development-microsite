<?php
  /**
   * Drush alias file, to be placed in your ~/.drush directory or the aliases
   * directory of your local Drush home. Once it's in place, clear drush cache:
   *
   * drush cc drush
   *
   * To see all your available aliases:
   *
   * drush sa
   */
  $aliases['ldc.dev'] = array(
    'uri' => 'ldc.dev.zilleem.com',
    'ssh-options' => '-p 22',
    'path-aliases' => array(
      '%files' => 'files',
      '%drush-script' => 'drush',
     ),
  );
