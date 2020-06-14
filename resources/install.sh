#! /bin/bash

echo "Début d'installation des dépendances"

touch /tmp/mqtt_dep
echo 0 > /tmp/mqtt_dep
apt-get -y install lsb-release php-pear
archi=`lscpu | grep Architecture | awk '{ print $2 }'`

if [ "$archi" == "x86_64" ]; then
if [ `lsb_release -i -s` == "Debian" ]; then
  wget http://repo.mosquitto.org/debian/mosquitto-repo.gpg.key
  apt-key add mosquitto-repo.gpg.key
  cd /etc/apt/sources.list.d/
  if [ `lsb_release -c -s` == "jessie" ]; then
    wget http://repo.mosquitto.org/debian/mosquitto-jessie.list -O mosquitto-jessie.list
    rm /etc/apt/sources.list.d/mosquitto-jessie.list
    cp -r mosquitto-jessie.list /etc/apt/sources.list.d/mosquitto-jessie.list
  fi
  if [ `lsb_release -c -s` == "stretch" ]; then
    wget http://repo.mosquitto.org/debian/mosquitto-stretch.list -O mosquitto-stretch.list
    rm /etc/apt/sources.list.d/mosquitto-stretch.list
    cp -r mosquitto-stretch.list /etc/apt/sources.list.d/mosquitto-stretch.list
  fi
  if [ `lsb_release -c -s` == "buster" ]; then
    wget http://repo.mosquitto.org/debian/mosquitto-buster.list -O mosquitto-buster.list
    rm /etc/apt/sources.list.d/mosquitto-buster.list
    cp -r mosquitto-buster.list /etc/apt/sources.list.d/mosquitto-buster.list
  fi
fi
fi
echo 10 > /tmp/mqtt_dep

apt-get update
echo 30 > /tmp/mqtt_dep
apt-get -y install mosquitto mosquitto-clients libmosquitto-dev
echo 60 > /tmp/mqtt_dep


phpv=`php --version | head -n 1 | cut -d " " -f 2 | cut -c 1-3`

apt-get -y install php$phpv-dev
if [[ -d "/etc/php/$phpv/cli/" && ! `cat /etc/php/$phpv/cli/php.ini | grep "mosquitto"` ]]; then
  echo "" | pecl install Mosquitto-beta
  echo 80 > /tmp/mqtt_dep
  echo "extension=mosquitto.so" | tee -a /etc/php/$phpv/cli/php.ini
fi
if [[ -d "/etc/php/$phpv/fpm/" && ! `cat /etc/php/$phpv/fpm/php.ini | grep "mosquitto"` ]]; then
  echo "extension=mosquitto.so" | tee -a /etc/php/$phpv/fpm/php.ini
  service php$phpv-fpm restart
fi
if [[ -d "/etc/php/$phpv/apache2/" && ! `cat /etc/php/$phpv/apache2/php.ini | grep "mosquitto"` ]]; then
  echo "extension=mosquitto.so" | tee -a /etc/php/$phpv/apache2/php.ini
  rm /tmp/mqtt_dep
  echo "Fin installation des dépendances"
  service apache2 restart
fi

rm /tmp/mqtt_dep

echo "Fin installation des dépendances"
