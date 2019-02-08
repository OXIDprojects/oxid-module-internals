#!/usr/bin/env bash
set -e
set -x
cd /ci/oxideshop/
php vendor/bin/runtests
cd /ci/oxideshop/vendor/oxid-community/moduleinternals
php /ci/oxideshop/vendor/bin/codecept run
