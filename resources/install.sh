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
    wget http://repo.mosquitto.org/debian/mosquitto-jessie.list
    rm /etc/apt/sources.list.d/mosquitto-jessie.list
    cp -r mosquitto-jessie.list /etc/apt/sources.list.d/mosquitto-jessie.list
  fi
fi
fi
echo 10 > /tmp/mqtt_dep

apt-get update
echo 30 > /tmp/mqtt_dep
apt-get -y install mosquitto mosquitto-clients libmosquitto-dev php5-dev
echo 60 > /tmp/mqtt_dep
if [[ -d "/etc/php5/cli/" && ! `cat /etc/php5/cli/php.ini | grep "mosquitto"` ]]; then
	echo "" | pecl install Mosquitto-alpha
  echo 80 > /tmp/mqtt_dep
	echo "extension=mosquitto.so" | tee -a /etc/php5/cli/php.ini
fi
if [[ -d "/etc/php5/fpm/" && ! `cat /etc/php5/fpm/php.ini | grep "mosquitto"` ]]; then
	echo "extension=mosquitto.so" | tee -a /etc/php5/fpm/php.ini
  service php5-fpm restart
fi
if [[ -d "/etc/php5/apache2/" && ! `cat /etc/php5/apache2/php.ini | grep "mosquitto"` ]]; then
	echo "extension=mosquitto.so" | tee -a /etc/php5/apache2/php.ini
  rm /tmp/mqtt_dep
  echo "Fin installation des dépendances"
  service apache2 restart
fi

rm /tmp/mqtt_dep

echo "Fin installation des dépendances"
