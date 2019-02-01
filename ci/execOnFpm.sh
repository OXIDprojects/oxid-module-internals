#!/usr/bin/env bash
#shift 1
docker exec -ti oxid_fpm.1.$(docker service ps -f 'name=oxid_fpm.1'  oxid_fpm -q --no-trunc) "$@"
