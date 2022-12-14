#!/bin/bash
cd /app
# make sure all composer components are installed
composer install
cd /app/docroot
# execute pending database updates
drush updb -y
# import the config
drush cim -y && drush cim -y && drush cim -y
# enable development modules
#drush en devel kint stage_file_proxy -y
# enable stage_file_proxy and configure settings
#drush cset stage_file_proxy.settings \
#origin http://ldc.dev.zilleem.com -y
#drush cset stage_file_proxy.settings hotlink true -y
# clear caches just for good measure
drush cr
# provide a login url
drush uli
