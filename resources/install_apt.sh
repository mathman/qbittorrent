#!/bin/bash

PROGRESS_FILE=/tmp/jeedom/qbittorrent/dependency
sudo touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "Lancement de l'installation/mise à jour des dépendances"

BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd ${BASEDIR}/qbittorrent/

function apt_install {
  sudo apt-get -y install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $1 - abort"
    sudo rm ${PROGRESS_FILE}
    exit 1
  fi
}

function npm_install {
  sudo npm install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $p - abort"
    sudo rm ${PROGRESS_FILE}
    exit 1
  fi
}

echo "Désinstallation des dependances"
echo 10 > ${PROGRESS_FILE}
sudo apt-get -y purge nodejs
sudo rm -rf node_modules
sudo rm package-lock.json
echo 20 > ${PROGRESS_FILE}
echo "Installation des dependances"
curl -sL https://deb.nodesource.com/setup_24.x | sudo -E bash -
echo 30 > ${PROGRESS_FILE}
sudo apt-get clean
echo 40 > ${PROGRESS_FILE}
sudo apt-get update
echo 50 > ${PROGRESS_FILE}
apt_install nodejs
echo 60 > ${PROGRESS_FILE}
# Npm
echo "Installation des dependances nodejs"
echo 70 > ${PROGRESS_FILE}
npm_install
echo 80 > ${PROGRESS_FILE}
echo 100 > ${PROGRESS_FILE}
echo "Everything is successfully installed!"
sudo rm ${PROGRESS_FILE}
