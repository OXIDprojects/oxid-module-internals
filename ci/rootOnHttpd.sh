#!/usr/bin/env bash
docker exec -e TERM=$TERM --user "0" -ti oxid_apache.1.$(docker service ps -f 'name=oxid_apache.1' -f 'desired-state=Running' oxid_apache -q --no-trunc) "$@"