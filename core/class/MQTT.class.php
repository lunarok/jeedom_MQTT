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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class MQTT extends eqLogic {
  /*     * *************************Attributs****************************** */
  public static function health() {
    $return = array();
    $mosqHost = config::byKey('mqttAdress', 'MQTT', 0);
    if ($mosqHost == '') {
      $mosqHost = '127.0.0.1';
    }
    $mosqPort = config::byKey('mqttPort', 'MQTT', 0);
    if ($mosqPort == '') {
      $mosqPort = '1883';
    }
    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    $server = socket_connect ($socket , $mosqHost, $mosqPort);

    $return[] = array(
      'test' => __('Mosquitto', __FILE__),
      'result' => ($server) ? __('OK', __FILE__) : __('NOK', __FILE__),
      'advice' => ($server) ? '' : __('Indique si Mosquitto est disponible', __FILE__),
      'state' => $server,
    );
    return $return;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = '';
    $return['state'] = 'nok';
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (is_object($cron) && $cron->running()) {
      $return['state'] = 'ok';
    }
    $return['launchable'] = 'ok';
    return $return;
  }
  public static function deamon_start($_debug = false) {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (!is_object($cron)) {
      throw new Exception(__('Tache cron introuvable', __FILE__));
    }
    $cron->run();
  }
  public static function deamon_stop() {
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    if (!is_object($cron)) {
      throw new Exception(__('Tache cron introuvable', __FILE__));
    }
    $cron->halt();
  }

  public static function daemon() {

    $mosqHost = config::byKey('mqttAdress', 'MQTT', 0);
    $mosqPort = config::byKey('mqttPort', 'MQTT', 0);
    $mosqId = config::byKey('mqttId', 'MQTT', 0);
    if ($mosqHost == '') {
      $mosqHost = '127.0.0.1';
    }
    if ($mosqPort == '') {
      $mosqPort = '1883';
    }
    if ($mosqId == '') {
      $mosqId = 'Jeedom';
    }
    //$mosqAuth = config::byKey('mqttAuth', 'MQTT', 0);
    $mosqUser = config::byKey('mqttUser', 'MQTT', 0);
    $mosqPass = config::byKey('mqttPass', 'MQTT', 0);
    //$mosqSecure = config::byKey('mqttSecure', 'MQTT', 0);
    //$mosqCA = config::byKey('mqttCA', 'MQTT', 0);
    //$mosqTree = config::byKey('mqttTree', 'MQTT', 0);
    log::add('MQTT', 'info', 'Paramètres utilisés, Host : ' . $mosqHost . ', Port : ' . $mosqPort . ', ID : ' . $mosqId);
    if (isset($mosqHost) && isset($mosqPort) && isset($mosqId)) {
      //https://github.com/mqtt/mqtt.github.io/wiki/mosquitto-php
      $client = new Mosquitto\Client($mosqId);
      //if ($mosqAuth) {
      //$client->setCredentials($mosqUser, $mosqPass);
      //}
      //if ($mosqSecure) {
      //$client->setTlsOptions($certReqs = Mosquitto\Client::SSL_VERIFY_PEER, $tlsVersion = 'tlsv1.2', $ciphers=NULL);
      //$client->setTlsCertificates($caPath = 'path/to/my/ca.crt');
      //}
      $client->onConnect('MQTT::connect');
      $client->onDisconnect('MQTT::disconnect');
      $client->onSubscribe('MQTT::subscribe');
      $client->onMessage('MQTT::message');
      $client->onLog('MQTT::logmq');
      $client->setWill('/jeedom', "Client died :-(", 1, 0);
      try {
        if (isset($mosqUser)) {
          $client->setCredentials($mosqUser, $mosqPass);
        }
        $client->connect($mosqHost, $mosqPort, 60);
        $client->subscribe('#', 1); // Subscribe to all messages
        //$client->loopForever();
        while (true) { $client->loop(); }
      }
      catch (Exception $e){
        //log::add('MQTT', 'error', $e->getMessage());
      }
    } else {
      log::add('MQTT', 'info', 'Tous les paramètres ne sont pas définis');
    }
  }

  public function stopDaemon() {
    $cron = cron::byClassAndFunction('MQTT', 'daemon');
    $cron->stop();
  }

  public static function connect( $r, $message ) {
    log::add('MQTT', 'info', 'Connexion à Mosquitto avec code ' . $r . ' ' . $message);
    config::save('status', '1',  'MQTT');
  }

  public static function disconnect( $r ) {
    log::add('MQTT', 'debug', 'Déconnexion de Mosquitto avec code ' . $r);
    config::save('status', '0',  'MQTT');
  }

  public static function subscribe( ) {
    log::add('MQTT', 'debug', 'Subscribe ');
  }

  public static function logmq( $code, $str ) {
    log::add('MQTT', 'debug', $code . ' : ' . $str);
  }


  public static function message( $message ) {
    log::add('MQTT', 'debug', 'Message ' . $message->payload . ' sur ' . $message->topic);
    $topic = $message->topic;
    $topicArray = explode("/", $topic);
    $cmdId = end($topicArray);
    $key = count($topicArray) - 1;
    unset($topicArray[$key]);
    $nodeid = (implode($topicArray,'/'));
    $value = $message->payload;

    $elogic = self::byLogicalId($nodeid, 'MQTT');
    if (is_object($elogic)) {
      $elogic->setStatus('lastCommunication', date('Y-m-d H:i:s'));
      $elogic->save();
    } else {
      log::add('MQTT', 'info', 'Equipement n existe pas, creation');
      $elogic = new MQTT();
      $elogic->setEqType_name('MQTT');
      $elogic->setLogicalId($nodeid);
      $elogic->setName($nodeid);
      $elogic->setIsEnable(true);
      $elogic->save();
      $elogic->setStatus('lastCommunication', date('Y-m-d H:i:s'));
      $elogic->setConfiguration('topic', $topic);
      $elogic->save();
    }

      log::add('MQTT', 'info', 'Message texte : ' . $value . ' pour information : ' . $cmdId . ' sur : ' . $nodeid);
      $cmdlogic = MQTTCmd::byEqLogicIdAndLogicalId($elogic->getId(),$cmdId);
      if (!is_object($cmdlogic)) {
        log::add('MQTT', 'info', 'Cmdlogic n existe pas, creation');
        $cmdlogic = new MQTTCmd();
        $cmdlogic->setEqLogic_id($elogic->getId());
        $cmdlogic->setEqType('MQTT');
        $cmdlogic->setIsVisible(1);
        $cmdlogic->setIsHistorized(0);
        $cmdlogic->setSubType('string');
        $cmdlogic->setLogicalId($cmdId);
        $cmdlogic->setType('info');
        $cmdlogic->setName( $cmdId );
        $cmdlogic->setConfiguration('topic', $topic);
		$cmdlogic->setConfiguration('parseJson', 0); //default don't parse json data
        $cmdlogic->save();
      }
      $cmdlogic->setConfiguration('value', $value);
      $cmdlogic->save();
      $cmdlogic->event($value);

      if ($value[0] == '{' && substr($value, -1) == '}' && $cmdlogic->getConfiguration('parseJson') == 1) {
        // payload is json
        $nodeid = $topic;
        $json = json_decode($value);
        foreach ($json as $cmdId => $value) {
          $topicjson = $topic . '{' . $cmdId . '}';
          log::add('MQTT', 'info', 'Message json : ' . $value . ' pour information : ' . $cmdId . ' sur : ' . $nodeid);
          $cmdlogic = MQTTCmd::byEqLogicIdAndLogicalId($elogic->getId(),$cmdId);
          if (!is_object($cmdlogic)) {
            log::add('MQTT', 'info', 'Cmdlogic n existe pas, creation');
            $cmdlogic = new MQTTCmd();
            $cmdlogic->setEqLogic_id($elogic->getId());
            $cmdlogic->setEqType('MQTT');
            $cmdlogic->setIsVisible(1);
            $cmdlogic->setIsHistorized(0);
            $cmdlogic->setSubType('string');
            $cmdlogic->setLogicalId($cmdId);
            $cmdlogic->setType('info');
            $cmdlogic->setName( $cmdId );
            $cmdlogic->setConfiguration('topic', $topicjson);
            $cmdlogic->save();
          }
          $cmdlogic->setConfiguration('value', $value);
          $cmdlogic->save();
          $cmdlogic->event($value);
        }
      }
  }

  public static function publishMosquitto( $subject, $message ) {
    log::add('MQTT', 'debug', 'Envoi du message ' . $message . ' vers ' . $subject);
    $mosqHost = config::byKey('mqttAdress', 'MQTT', 0);
    $mosqPort = config::byKey('mqttPort', 'MQTT', 0);
    $mosqId = config::byKey('mqttId', 'MQTT', 0);
    if ($mosqHost == '') {
      $mosqHost = '127.0.0.1';
    }
    if ($mosqPort == '') {
      $mosqPort = '1883';
    }
    if ($mosqId == '') {
      $mosqId = 'Jeedom';
    }
    $mosqPub = $mosqId . '_pub';
    $mosqUser = config::byKey('mqttUser', 'MQTT', 0);
    $mosqPass = config::byKey('mqttPass', 'MQTT', 0);
    $publish = new Mosquitto\Client($mosqPub);
    if (isset($mosqUser)) {
      $publish->setCredentials($mosqUser, $mosqPass);
    }
    $publish->connect($mosqHost, $mosqPort, 60);
    $publish->publish($subject, $message, 1, false);
    $publish->disconnect();
    unset($publish);
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'MQTT_dep';

    //lib PHP exist
  //  $libfpm = exec('grep "mosquitto" /etc/php5/fpm/php.ini');
    $libcli = exec('grep "mosquitto" /etc/php5/cli/php.ini');
    $libphp = extension_loaded('mosquitto');

    if (file_exists('/tmp/mqtt_dep')) {
      $return['state'] = 'in_progress';
    } else if (!$libphp) {
      $return['state'] = 'ko';
      // mise en log de l'état des dép
      if ($libcli == 'extension=mosquitto.so') {
        $libcli = 'ok';
      } else {
        $libcli = 'ko';
      }
      log::add('MQTT', 'error', 'Lib CLI : ' . $libcli);
    } else {
      $return['state'] = 'ok';
    }
    return $return;
  }

  public static function dependancy_install() {
    log::add('MQTT','info','Installation des dépéndances');
    $resource_path = realpath(dirname(__FILE__) . '/../../resources');
    passthru('sudo nohup /bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('MQTT_dep') . ' 2>&1 &');
    return true;
  }

}



class MQTTCmd extends cmd {

  public function execute($_options = null) {


    switch ($this->getType()) {

      case 'info' :
      return $this->getConfiguration('value');
      break;

      case 'action' :
      $request = $this->getConfiguration('request');
      $topic = $this->getConfiguration('topic');

      switch ($this->getSubType()) {
        case 'slider':
        $request = str_replace('#slider#', $_options['slider'], $request);
        break;
        case 'color':
        $request = str_replace('#color#', $_options['color'], $request);
        break;
        case 'message':
        if ($_options != null)  {

          $replace = array('#title#', '#message#');
          $replaceBy = array($_options['title'], $_options['message']);
          if ( $_options['title'] == '') {
            throw new Exception(__('Le sujet ne peuvent être vide', __FILE__));
          }
          $request = str_replace($replace, $replaceBy, $request);

        }
        else
        $request = 1;

        break;
        default : $request == null ?  1 : $request;

      }

      $eqLogic = $this->getEqLogic();

      MQTT::publishMosquitto(
      $topic ,
      $request );

      $result = $request;


      return $result;
    }

    return true;

  }

}
