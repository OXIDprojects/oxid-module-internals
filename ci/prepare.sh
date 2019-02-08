#!/usr/bin/env bash
./ci/run_stack.sh
sleep 2
#docker stack ps --no-trunc oxid
#docker ps
#containerName=oxid_fpm.1.$(docker service ps -f 'name=oxid_fpm.1'  -f 'desired-state=Running' oxid_fpm -q --no-trunc|head -n 1);
#containerId=$(docker inspect --format="{{.ID}}" $containerName)
#docker exec -ti $containerId pwd

./ci/execOnFpm.sh bash /module/ci/install.sh
