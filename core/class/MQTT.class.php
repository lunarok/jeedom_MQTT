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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class MQTT extends eqLogic {

  public function preSave() {
	if (config::byKey('mqttAuto', 'MQTT', 0) == 0) {  // manual mode
	    //check if some change needs reloading daemon
		$_logicalId = $this->getLogicalId();
		$_topic = $this->getConfiguration('topic');
		$_wcard = $this->getConfiguration('wcard');
		$_qos = $this->getConfiguration('Qos');
		if ($this->getConfiguration('isChild') != "1") {
			if ($_logicalId != $_topic) {
				$this->setLogicalId($_topic);
				$this->setConfiguration('reload_d', '1');
			}
			else $this->setConfiguration('reload_d', '0');

			if ($this->getConfiguration('wcard') != $this->getConfiguration('prev_wcard')) {
				$this->setConfiguration('prev_wcard',$_wcard);
				$this->setConfiguration('reload_d', '1');
			}
			if(!$this->getConfiguration('wcard') ) {
				$this->setConfiguration('wcard','+');
				$this->setConfiguration('prev_wcard','+');
				$this->setConfiguration('reload_d', '1');
			}
			if ($this->getConfiguration('Qos') != $this->getConfiguration('prev_Qos')) {
				$this->setConfiguration('prev_Qos',$_qos);
				$this->setConfiguration('reload_d', '1');
			}
			if(!$this->getConfiguration('Qos') ) {
				$this->setConfiguration('Qos','+');
				$this->setConfiguration('prev_Qos','+');
				$this->setConfiguration('reload_d', '1');
			}
		}
	}
  }
  public function postSave() {
	if (config::byKey('mqttAuto', 'MQTT', 0) == 0) {  // manual mode
		if ($this->getConfiguration('reload_d') == "1") {
			$cron = cron::byClassAndFunction('MQTT', 'daemon');
			//Restarting mqtt daemon
			if (is_object($cron) && $cron->running()) {
				$cron->halt();
				$cron->run();
			}
		}
	}

  }


  public function postRemove() {
	if (config::byKey('mqttAuto', 'MQTT', 0) == 0) {  // manual mode
		$cron = cron::byClassAndFunction('MQTT', 'daemon');
		//Restarting mqtt daemon
		if (is_object($cron) && $cron->running()) {
			$cron->halt();
			$cron->run();
		}
	}
  }


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

    	$mosqHost = config::byKey('mqttAdress', 'MQTT', '127.0.0.1');
    	$mosqPort = config::byKey('mqttPort', 'MQTT', '1883');
    	$mosqId = config::byKey('mqttId', 'MQTT', 'Jeedom');
	$mosqTopic = config::byKey('mqttTopic', 'MQTT', '#');
	$mosqQos = config::byKey('mqttQos', 'MQTT', 1);

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

		if (config::byKey('mqttAuto', 'MQTT', 0) == 0) {  // manual mode
			foreach (eqLogic::byType('MQTT', true) as $mqtt) {
				if ($mqtt->getConfiguration('isChild') != "1") {
					$devicetopic = $mqtt->getConfiguration('topic');
					$wildcard    = $mqtt->getConfiguration('wcard');
					$qos         = (int)$mqtt->getConfiguration('Qos');
					if (!$qos) $qos = 1;
					if($wildcard) {
						$fulltopic = $devicetopic . "/" . $wildcard;
					}
					else $fulltopic = $devicetopic;
					log::add('MQTT', 'info', 'Subscribe to topic ' . $fulltopic);
					$client->subscribe($fulltopic, $qos); // Subscribe to topic
				}
			}
		}
		else {
			$client->subscribe($mosqTopic, $mosqQos); // !auto: Subscribe to root topic
			log::add('MQTT', 'debug', 'Subscribe to topic ' . $mosqtopic);
		}

        //$client->loopForever();
        while (true) { $client->loop(); }
      }
      catch (Exception $e){
        log::add('MQTT', 'error', $e->getMessage());
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

    if(!ctype_print($topic) || empty($topic)) {
      log::add('MQTT', 'debug', 'Message skipped : "'.$message->topic.'" is not a valid topic');
      return;
    }

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

      $elogic = new MQTT();
      $elogic->setEqType_name('MQTT');
      $elogic->setLogicalId($nodeid);
      $elogic->setName($nodeid);
      $elogic->setIsEnable(true);
      $elogic->setStatus('lastCommunication', date('Y-m-d H:i:s'));
      $elogic->setConfiguration('topic', $nodeid);
	  $elogic->setConfiguration('wcard', '+');
	  $elogic->setConfiguration('prev_wcard', '+');
	  $elogic->setConfiguration('Qos', '1');
	  $elogic->setConfiguration('prev_Qos', '1');
	  $elogic->setConfiguration('isChild', '1');
	  $elogic->setConfiguration('reload_d', '0');
	  log::add('MQTT', 'info', 'Saving device ');
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

  public static function publishMosquitto( $subject, $message, $qos , $retain) {
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
    $publish->publish($subject, $message, $qos, $retain);
	for ($i = 0; $i < 100; $i++) {
    // Loop around to permit the library to do its work
    $publish->loop(1);
	}
    $publish->disconnect();
    unset($publish);
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'MQTT_dep';
    $return['state'] = 'nok';

    $cmd = "dpkg -l | grep mosquitto";
    exec($cmd, $output, $return_var);
    //lib PHP exist
    $libphp = extension_loaded('mosquitto');

    if ($output[0] != "" && $libphp) {
      $return['state'] = 'ok';
    }
    log::add('MQTT', 'debug', 'Lib : ' . print_r(get_loaded_extensions(),true));

    return $return;
  }

  public static function dependancy_install() {
    log::add('MQTT','info','Installation des dépéndances');
    $resource_path = realpath(dirname(__FILE__) . '/../../resources');
    passthru('sudo /bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('MQTT_dep') . ' 2>&1 &');
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
	  $qos = $this->getConfiguration('Qos');
	  if ($this->getConfiguration('retain') == 0) $retain = false;
	  else $retain = true;
	  
	  if ($qos == NULL) $qos = 1; //default to 1

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
	  $request = jeedom::evaluateExpression($request);
      $eqLogic = $this->getEqLogic();

      MQTT::publishMosquitto(
      $topic ,
      $request ,
	  $qos,
	  $retain );

      $result = $request;
      return $result;
    }
    return true;
  }
}
