#!/usr/bin/env bash

cd /module/ci/www
composer create-project oxid-esales/oxideshop-project . dev-b-6.0-ce

cd /var/www/source
sed -i -e "s@<dbHost>@oxid6_mysql@g; s@<dbName>@oxid@g; s@<dbUser>@oxid@g; s@<dbPwd>@oxid@g; s@<sShopURL>@http://oxid6_apache/@g; s@<sShopDir>@/var/www/source@" config.inc.php

sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g; s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml

composer config minimum-stability dev

#Module Registrieren
composer clear-cache
composer config repo.packagist false
composer config repositories.travis path /module

composer require "oxid-community/moduleinternals:*"

./vendor/bin/runtests
