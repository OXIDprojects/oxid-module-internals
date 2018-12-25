# OXID Installieren
cd ~/
mkdir OXID
cd OXID

if [ ! -f selenium-server-standalone-2.47.1.jar ]; then
  wget https://raw.githubusercontent.com/OXID-eSales/oxvm_assets/master/selenium-server-standalone-2.47.1.jar
fi
if [ ! -f firefox-mozilla-build_31.0-0ubuntu1_amd64.deb ]; then
  wget https://raw.githubusercontent.com/OXID-eSales/oxvm_assets/master/firefox-mozilla-build_31.0-0ubuntu1_amd64.deb
fi

sudo dpkg -i firefox-mozilla-build_31.0-0ubuntu1_amd64.deb
#sudo apt-get install -f -y
xvfb-run --server-args="-screen 0, 1024x768x24" ${TRAVIS_BUILD_DIR}/start_selenium.sh

composer create-project oxid-esales/oxideshop-project . dev-b-6.1-ce
sed -i -e "s@<dbHost>@127.0.0.1@g" source/config.inc.php
sed -i -e "s@<dbName>@oxid@g" source/config.inc.php
sed -i -e "s@<dbUser>@root@g" source/config.inc.php
sed -i -e "s@<dbPwd>@@g" source/config.inc.php
sed -i -e "s@<sShopURL>@http://127.0.0.1@g" source/config.inc.php
sed -i -e "s@<sShopDir>@/home/travis/OXID/source@g" source/config.inc.php
sed -i -e "s@<sCompileDir>@/home/travis/OXID/source/tmp@g" source/config.inc.php
sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g" test_config.yml
sed -i -e "s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml

#Module Registrieren
composer clear-cache
composer config repo.packagist false
composer config minimum-stability dev
composer config repositories.travis path ${TRAVIS_BUILD_DIR}
composer require "oxid-community/moduleinternals:*"
