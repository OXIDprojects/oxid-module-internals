#!/usr/bin/env bash
cd /var/www/source
sed -i -e "s@partial_module_paths: null@partial_module_paths: oxcom/moduleinternals@g" test_config.yml
sed -i -e "s@run_tests_for_shop: true@run_tests_for_shop: false@g" test_config.yml

composer config minimum-stability dev

#Module Registrieren
composer clear-cache
composer config repo.packagist false
composer config repositories.travis path /module

composer require "oxid-community/moduleinternals:*"
