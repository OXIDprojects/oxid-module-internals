: "${OXID:=6.1}"
echo $OXID
docker swarm init
docker stack deploy --compose-file docker-compose.yml oxid
docker run --network=oxid_proxy -ti keywanghadamioxid/php-apache-full:$OXID bash /module/test.sh
