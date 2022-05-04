#! /bin/bash

echo "Début d'installation des dépendances"

touch /tmp/mqtt_dep
echo 0 > /tmp/mqtt_dep

phpv=`php --version | head -n 1 | cut -d " " -f 2 | cut -c 1-3`

apt-get -y install php$phpv-dev
echo 60 > /tmp/mqtt_dep

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
  service apache2 restart
fi

rm /tmp/mqtt_dep
echo "Fin installation des dépendances"