#!/bin/bash
set -eo pipefail
if [[ $(id -u) -ne 0 ]]; then
  exec sudo bash "${BASH_SOURCE[0]}"
fi
mkdir -p /tmp/NDNrspec

install_packages() {
  if [[ -f /tmp/NDNrspec/install_packages.done ]]; then
    return
  fi

  sed -i 's|us\.archive\.ubuntu\.com|archive\.ubuntu\.com|' /etc/apt/sources.list
  echo 'Dpkg::Options {
    "--force-confdef";
    "--force-confold";
  }
  APT::Install-Recommends "no";
  APT::Install-Suggests "no";' > /etc/apt/apt.conf.d/80custom

  apt-get -y -qq update
  apt-get -y -qq install ca-certificates curl htop jq

  curl -sfL https://get.docker.com | bash
  awk -F: '$6~"^/users/" {printf "usermod -a -G docker %s\n", $1}' /etc/passwd | sh

  touch /tmp/NDNrspec/install_packages.done
}

prepare_netif() {
  if [[ -f /tmp/NDNrspec/ifname.txt ]]; then
    return
  fi
  install_packages

  if ip -j addr | jq -e '[.[].addr_info[] | select(.local | startswith("2001:6a8:1d80:"))] | length > 0' >/dev/null; then
    wget -O- -nv --ciphers DEFAULT@SECLEVEL=1 https://www.wall2.ilabt.iminds.be/enable-nat.sh | bash
  fi

  local MATCH=$(jq -nr --argjson L "$(ip -j link)" --argjson J "$J" '
    $J.nodes[] | . as $node |
    select(map($L[] | select(.address == $node.mac)) | length > 0) |
    [
      $node.id,
      ($L[] | select(.address == $node.mac) | .ifname)
    ] | @tsv')
  if [[ -z $MATCH ]]; then
    echo 'cannot match current node' >/dev/stderr
    return 1
  fi

  echo $MATCH | awk '{
    printf "sudo hostnamectl set-hostname %s\n", $1;
    printf "echo %s >/tmp/NDNrspec/ifname.txt\n", $2;
  }' | sh
}

download_ndndpdk() {
  if [[ -f /tmp/NDNrspec/download_ndndpdk.done ]]; then
    return
  fi
  prepare_netif

  local NDNDPDK_DOCKER_IMAGE=$(echo "$J" | jq -r '.ndndpdkDockerImage')
  if [[ ${NDNDPDK_DOCKER_IMAGE} =~ docker.yoursunny.dev/ ]]; then
    local REGISTRY_CLIENT_BIN=/tmp/NDNrspec/Docker-registry-NDN-client.exe
    curl -o ${REGISTRY_CLIENT_BIN} -fL https://docker.yoursunny.dev/client/linux-amd64/client
    chmod +x ${REGISTRY_CLIENT_BIN}
    ${REGISTRY_CLIENT_BIN} &
    local REGISTRY_CLIENT_PID=$!
    sleep 10

    NDNDPDK_DOCKER_IMAGE_PROXY=${NDNDPDK_DOCKER_IMAGE/docker.yoursunny.dev/localhost:5000}
    if docker pull ${NDNDPDK_DOCKER_IMAGE_PROXY}; then
      docker tag ${NDNDPDK_DOCKER_IMAGE_PROXY} ndn-dpdk
    else
      echo 'docker pull over NDN failed'
    fi
    kill ${REGISTRY_CLIENT_PID}
  fi
  if ! docker image inspect ndn-dpdk >/dev/null; then
    docker pull ${NDNDPDK_DOCKER_IMAGE}
    docker tag ${NDNDPDK_DOCKER_IMAGE} ndn-dpdk
  fi

  sudo mkdir -p /usr/local/bin /usr/local/share
  CTID=$(docker container create ndn-dpdk)
  docker cp $CTID:/usr/local/bin/dpdk-devbind.py - | sudo tar -x -C /usr/local/bin
  docker cp $CTID:/usr/local/bin/dpdk-hugepages.py - | sudo tar -x -C /usr/local/bin
  docker cp $CTID:/usr/local/share/ndn-dpdk - | sudo tar -x -C /usr/local/share
  docker container rm $CTID

  dpdk-hugepages.py --pagesize 2M --mount
  for NODE in /sys/devices/system/node/node*; do
    NODE=$(basename $NODE)
    NODE=${NODE/node/}
    while ! dpdk-hugepages.py --pagesize 2M --node $NODE --reserve 4G; do
      sleep 1
    done
  done

  touch /tmp/NDNrspec/download_ndndpdk.done
}

start_ndndpdk() {
  docker volume create run-ndn
  docker rm -f ndndpdk-svc
  docker run -d --name ndndpdk-svc \
    -p 127.0.0.1:3030:3030 \
    --cap-add IPC_LOCK --cap-add NET_ADMIN --cap-add NET_RAW --cap-add SYS_ADMIN --cap-add SYS_NICE \
    --mount type=bind,source=/dev/hugepages,target=/dev/hugepages \
    --mount type=volume,source=run-ndn,target=/run/ndn \
    ndn-dpdk

  local IFNAME=$(cat /tmp/NDNrspec/ifname.txt)
  local NETNS=$(docker inspect --format='{{.State.Pid}}' ndndpdk-svc)
  sudo ip link set $IFNAME netns $NETNS
  docker exec ndndpdk-svc ip link set $IFNAME up

  GQLSERVER=$(docker inspect -f 'http://{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}:3030/' ndndpdk-svc)
  (
    echo '#!/bin/bash'
    echo 'exec docker run -i --rm ndn-dpdk ndndpdk-ctrl --gqlserver '$GQLSERVER' "$@"'
  ) > /usr/local/bin/ndndpdk-ctrl
  chmod +x /usr/local/bin/ndndpdk-ctrl
}

activate_forwarder() {
  jq -n '{
    eal: {
      lcoresPerNuma: { "0": 6 },
    },
    lcoreAlloc: {
      RX: { "0": 1 },
      TX: { "0": 1 },
      FWD: { "0": 2 },
      CRYPTO: { "0": 1 }
    },
    mempool: {
      DIRECT: { capacity: 65535, dataroom: 2200 },
      INDIRECT: { capacity: 131071 }
    }
  }' | ndndpdk-ctrl activate-forwarder
}

setup_face_routes() {
  jq -nr --arg id "$(hostname -s)" --argjson J "$J" '
    $J.nodes[] | select(.id==$id) | .mac as $local |
    $J.nodes[] | select(.id!=$id) | (
      ("FACEID=$(ndndpdk-ctrl create-ether-face --local " + $local + " --remote " + .mac + " | jq -r .id)"),
      ("ndndpdk-ctrl insert-fib --name /" + .id + " --nexthop $FACEID")
    )
  ' | sh
}

start_jrproxy() {
  GQLSERVER=$(docker inspect -f 'http://{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}:3030/' ndndpdk-svc)
  docker rm -f ndndpdk-jrproxy
  docker run -d --name ndndpdk-jrproxy \
    -p 127.0.0.1:6345:6345 \
    ndn-dpdk ndndpdk-jrproxy --listen 0.0.0.0:6345 --gqlserver $GQLSERVER
}
