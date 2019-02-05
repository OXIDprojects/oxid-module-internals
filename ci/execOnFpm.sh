#!/usr/bin/env bash
#shift 1
docker exec -e TERM=$TERM --user "${UID}:${GID}" -ti oxid_fpm.1.$(docker service ps -f 'name=oxid_fpm.1'  -f 'desired-state=Running' oxid_fpm -q --no-trunc|head -n 1) "$@"
