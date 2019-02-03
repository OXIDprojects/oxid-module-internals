#!/usr/bin/env bash
: "${OXID:=6.1}"
echo $OXID
docker swarm init
docker stack deploy --compose-file ci/docker-stack.yml oxid
sleep 1
ci/execOnFpm.sh bash /module/ci/install.sh
