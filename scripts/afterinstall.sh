#!/bin/bash
# Set ownership for all folders
chown -R ec2-user:apache /var/www/html/
find /var/www/html/ -type d -exec chmod 775 {} \;
find /var/www/html/ -type f -exec chmod 664 {} \;
# set sym link for files directory
if [ "$DEPLOYMENT_GROUP_NAME" == "Drupal8_dev_LDC" ]
then
    ln -snf /mnt/efs/ldc_dev/files /var/www/html/docroot/sites/default/files
fi
if [ "$DEPLOYMENT_GROUP_NAME" == "Drupal8_prod_LDC" ]
then
    ln -snf /mnt/efs/ldc_prod/files /var/www/html/docroot/sites/default/files
fi
chown -R apache:apache /var/www/html/docroot/sites/default/files
