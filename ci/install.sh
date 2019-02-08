#!/usr/bin/env bash
set -e
COMPOSER_NO_INTERACTION=1
cd /ci/oxideshop/
rm -rf source
composer install
touch source/.gitkeep
sed -i -e "s@<dbHost>@mysql@g; s@<dbName>@oxid@g; s@<dbUser>@oxid@g; s@<dbPwd>@oxid@g; s@'<sShopURL>'@'http://'.\$_SERVER['HTTP_HOST']@g; s@'<sShopDir>'@__DIR__@; s@'<sCompileDir>'@__DIR__ . '/tmp'@" source/config.inc.php
sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g; s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml

./vendor/bin/runtests

