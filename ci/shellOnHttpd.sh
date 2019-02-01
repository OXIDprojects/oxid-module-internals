#!/usr/bin/env bash
docker exec --user "${UID}:${GID}" -ti oxid_apache.1.$(docker service ps -f 'name=oxid_apache.1'  oxid_apache -q --no-trunc) /bin/bash