#!/usr/bin/env bash
./ci/run_stack.sh
sleep 2
docker stack ps --no-trunc oxid
./ci/execOnFpm.sh bash /module/ci/install.sh
docker stack ps --no-trunc oxid
#/usr/local/apache2/htdocs
