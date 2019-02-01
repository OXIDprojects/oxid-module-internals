#!/usr/bin/env bash
: "${OXID:=6.1}"
echo $OXID
docker swarm init
docker stack deploy --compose-file ci/docker-stack.yml oxid
ci/execOnFpm.sh /module/ci/install.sh
