#!/usr/bin/env bash
ci/run_stack.sh
sleep 1
ci/execOnFpm.sh bash /module/ci/install.sh
#/usr/local/apache2/htdocs
