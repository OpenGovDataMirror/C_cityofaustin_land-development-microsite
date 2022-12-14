#!/bin/bash
# create the local config directory if it doesn't already exist
if [ ! -d /app/config/local ]; then mkdir /app/config/local; fi
# add lando settings
if [ ! -f /app/docroot/sites/default/settings.local.php ]; then
  cp /app/docroot/sites/default/lando.settings.php \
  /app/docroot/sites/default/settings.local.php
fi
# add drush settings
cp /app/lando/drush/ldc.aliases.drushrc.php \
~/.drush/ldc.aliases.drushrc.php
cp /app/lando/drush/drushrc.php ~/.drush/drushrc.php
