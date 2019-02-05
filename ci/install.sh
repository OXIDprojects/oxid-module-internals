#!/usr/bin/env bash
COMPOSER_NO_INTERACTION=1
cd /var/www/oxideshop/source
composer install

sed -i -e "s@<dbHost>@mysql@g; s@<dbName>@oxid@g; s@<dbUser>@oxid@g; s@<dbPwd>@oxid@g; s@<sShopURL>@http://oxid6_apache/@g; s@<sShopDir>@/ci/oxideshop/source@; s@<sCompileDir>@/ci/oxideshop/source/tmp@" source/config.inc.php

sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g; s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml

./vendor/bin/runtests

