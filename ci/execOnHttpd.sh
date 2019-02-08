#!/usr/bin/env bash
GID="$(id -g $(whoami))"
#docker exec -e TERM=$TERM --user "${UID}:${GID}" -ti oxid_apache.1.$(docker service ps -f 'name=oxid_apache.1' -f 'desired-state=Running' oxid_apache -q --no-trunc) "$@"
docker-compose -e TERM=$TERM exec apache "$@"