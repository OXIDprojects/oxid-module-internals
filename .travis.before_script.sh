#!/usr/bin/env bash
# OXID Installieren
cd ~/
mkdir OXID
cd OXID
composer create-project oxid-esales/oxideshop-project . dev-b-${OXID}-ce
sed -i -e "s@<dbHost>@127.0.0.1@g; s@<dbName>@oxid@g; s@<dbUser>@root@g; s@<dbPwd>@@g; s@<sShopURL>@http://127.0.0.1@g" source/config.inc.php
sed -i -e "s@<sShopDir>@/home/travis/OXID/source@g; s@<sCompileDir>@/home/travis/OXID/source/tmp@g" source/config.inc.php
sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g" test_config.yml
sed -i -e "s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml
cat test_config.yml
composer config minimum-stability dev

#Module Registrieren
#composer config repo.packagist false
composer config repositories.travis path ${TRAVIS_BUILD_DIR}
composer clear-cache
composer require "oxid-community/moduleinternals:*"

#debug: is the file itself ok
cat vendor/bin/runtests


#debug: trying to execute tests here to find errors
echo "starting test test"
vendor/bin/runtests

#debug checking log files
cat log/*