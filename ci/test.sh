#!/usr/bin/env bash
cd /ci/oxideshop/source
php /vendor/bin/runtests
cd /ci/oxideshop/source/vendor/oxid-community/moduleinternals
php /ci/oxideshop/vendor/bin/codecept run
