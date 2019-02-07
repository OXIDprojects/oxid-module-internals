#!/usr/bin/env bash
cd /var/www/source
php /vendor/bin/runtests
cd /var/www/source/vendor/oxid-community/moduleinternals
php /var/www/source/vendor/bin/codecept run