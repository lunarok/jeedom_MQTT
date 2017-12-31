<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function MQTT_install() {
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('MQTT');
        $cron->setFunction('daemon');
        $cron->setEnable(1);
        $cron->setDeamon(1);
        $cron->setSchedule('* * * * *');
        $cron->setTimeout('1440');
        $cron->save();
    }
}

function MQTT_update() {
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('MQTT');
        $cron->setFunction('daemon');
        $cron->setEnable(1);
        $cron->setDeamon(1);
        $cron->setSchedule('* * * * *');
        $cron->setTimeout('1440');
        $cron->save();
    }
}

function MQTT_remove() {
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (is_object($cron)) {
        $cron->stop();
        $cron->remove();
    }
    log::add('MQTT','info','Suppression extension');
    $resource_path = realpath(dirname(__FILE__) . '/../resources');
    passthru('sudo /bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('MQTT_dep') . ' 2>&1 &');
    return true;
}

?>
