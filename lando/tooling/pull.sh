#!/bin/bash
# make sure we can write to docroot/sites/default
chmod -R ug+w /app/docroot/sites/default
# pull down the latest code changes
git pull origin develop
# fix file permissions
if [ ! -d ~/.drush/file_permissions ]; then
  drush dl file_permissions --destination=~/.drush
  drush cc drush
fi
drush fp
