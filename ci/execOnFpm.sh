#!/usr/bin/env bash
set -e
set -x
#shift 1
GID="$(id -g $(whoami))"
#docker exec -e TERM=$TERM --user "${UID}:${GID}" -ti oxid_fpm.1.$(docker service ps -f 'name=oxid_fpm.1'  -f 'desired-state=Running' oxid_fpm -q --no-trunc|head -n 1) "$@"
docker-compose exec -e TERM=$TERM  fpm "$@"
