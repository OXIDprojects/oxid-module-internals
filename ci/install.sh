#!/usr/bin/env bash
set -e
set -x
COMPOSER_NO_INTERACTION=1
cd /ci/oxideshop/
rm -rf source
rm -f test_config.yml
composer install --no-interaction
touch source/.gitkeep
sed -i -e "s@<dbHost>@mysql@g; s@<dbName>@oxid@g; s@<dbUser>@oxid@g; s@<dbPwd>@oxid@g; s@'<sShopURL>'@'http://'.\$_SERVER['HTTP_HOST']@g; s@'<sShopDir>'@__DIR__@; s@'<sCompileDir>'@__DIR__ . '/tmp'@; s@sLogLevel = 'error'@sLogLevel = 'info'@g" source/config.inc.php
sed -i -e "s@shop_tests_path: tests@shop_tests_path: vendor/oxid-esales/oxideshop-ce/tests@g; s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g; s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml
php vendor/oxid-esales/testing-library/bin/reset-shop