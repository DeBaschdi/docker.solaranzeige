<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2019]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Ver?ffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Übertragen der Daten an einen MQTT-Broker und das Empfangen
//  von Daten.
//
//  Diese Funktion ist nur eingeschaltet, wenn in der user.config.php
//  $MQTT = true  eingetragen ist.
//
*****************************************************************************/
//
//
//
$Tracelevel = 7;  //  1 bis 10  10 = Debug

$basedir = dirname(__FILE__,2);
require($basedir."/library/base.inc.php");

if ($MQTT == false) {
  //  Es sollen keine Daten an den MQTT Broker übermittelt werden.
  exit;
}

$aktuelleDaten = array();
$Startzeit = time();
$Auswahl = array();
$AuswahlNeu = array();
$openWB = false;


/******************************************************************************
//  Die aktuellen Daten werden aus der Pipe gelesen
//  DATEN SENDEN       DATEN SENDEN        DATEN SENDEN         DATEN SENDEN
******************************************************************************/
$fifoPath = "/var/www/pipe/pipe";
if (! file_exists($fifoPath)) {
  Log::write("Pipe exestiert nicht. Nur Info, kein Fehler...Exit.","MQT",5);
  exit;
}

$fifo = fopen($fifoPath, "r+");
stream_set_blocking($fifo, false);
stream_set_timeout ($fifo, 5);
$PipeDaten = false;

$MQTTDaten = array();


$client = new Mosquitto\Client('Solaranzeige');
$client->onConnect([$funktionen,'mqtt_connect']);
$client->onDisconnect([$funktionen, 'mqtt_disconnect']);
$client->onPublish([$funktionen,'mqtt_publish']);
$client->onSubscribe([$funktionen,'mqtt_subscribe']);
$client->onMessage([$funktionen,'mqtt_message']);

if (!empty($MQTTBenutzer) and !empty($MQTTKennwort)) {
  $client->setCredentials($MQTTBenutzer, $MQTTKennwort);
}
if ($MQTTSSL) {
  $client->setTlsCertificates($basedir."/config/ca.crt");
  $client->setTlsInsecure(1);
}

$rc = $client->connect($MQTTBroker, $MQTTPort, $MQTTKeepAlive);
if ($rc == 14) {
  Log::write("MQTT Client connect nicht möglich. UserID + Kennwort?","MQT",7);
  exit;
}
for ($i=1;$i<200;$i++) {
  // Warten bis der connect erfolgt ist.
  if (empty($MQTTDaten)) {
    $client->loop(100);
  }
  else {
    break;
  }
}

Log::write($MQTTDaten["MQTTConnectReturnText"],"MQT",8);

/*****************************************************************************
//  Subscribe   Subscribe   Subscribe   Subscribe   Subscribe   Subscribe
//  QoS = 0 !!
//  DATEN EMPFANGEN    DATEN EMPFANGEN    DATEN EMPFANGEN    DATEN EMPFANGEN
*****************************************************************************/

if (is_file($basedir."/config/1.user.config.php")) {
  // Multi-Regler-Version
  for ($mra_i = 1; $mra_i < 7; $mra_i++) {
    if (is_file($basedir."/config/".$mra_i.".user.config.php")) {
      require($basedir."/config/".$mra_i.".user.config.php");
      foreach($MQTTTopic as $key=>$wert) {
        $client->subscribe($wert, 0);       // Subscribe   QoS = 0
      }
      if (!empty($MQTTAuswahl)) {
        $AuswahlNeu = explode(",",$MQTTAuswahl);
      }
      if (count($AuswahlNeu) > 0) {
        $Auswahl = array_merge($Auswahl,$AuswahlNeu );
        $AuswahlNeu = array();
        Log::write("MQTT Topic Auswahl in ".$mra_i.".user.config.php\n".var_export($Auswahl,1),"MQT",9);
      }
    }
  }
}
else {
  // Single-Geräte-Version
  foreach($MQTTTopic as $key=>$wert) {
    $client->subscribe($wert, 0);           // Subscribe
  }
  if (!empty($MQTTAuswahl)) {
    $Auswahl = explode(",",$MQTTAuswahl);
  }
}
$client->loop(10);


do {

  if (isset($MQTTDaten["MQTTMessageReturnText"]) and $MQTTDaten["MQTTMessageReturnText"] == "RX-OK") {

    /*************************************************************************
    //  MQTT Meldungen empfangen. Subscribing    Subscribing    Subscribing
    //  Broker auslesen...
    *************************************************************************/
    Log::write(print_r($MQTTDaten,1),"MQT",10);
    mqttDatenAuswerten($MQTTDaten);
    $MQTTDaten["MQTTMessageReturnText"] = "RX-NO";
  }

  $Daten = fgets($fifo,1024);
  if (empty($Daten)) {
    sleep(1);
    $client->loop(1);
    continue;
  }
  else {
    $Teile = explode(" ",$Daten,2);
    if (substr($Daten,-1) == "|") {
      Log::write("PIPE ausgelesen und versendet.","MQT",8);
      $PipeDaten = true;
    }
    if (isset($Teile[1])) {
      $aktuelleDaten[$Teile[0]] = trim($Teile[1]);
    }
  }

  if ($PipeDaten)  {

    /****************************************************************************
    //  MQTT Meldungen senden. Publishing Publishing  Publishing  Publishing
    //  Achtung! Ab version 4.5.0 wird auch das MQTTGeraet Übertragen.
    //
    //  $client->publish($topic, $payload, $qos, $retain); (retrain = true/false)
    ****************************************************************************/

    foreach($aktuelleDaten as $key=>$wert) {
      if (count($Auswahl) == 0 or in_array(strtolower($key), $Auswahl) or in_array($key, $Auswahl)or substr($key,0,6) == "openWB") {
        if (substr($key,0,6) == "openWB") {
          $topic = $key;
        }
        else {
          $topic = "solaranzeige/".strtolower($key);
        }
        Log::write(": ".$key." -> ".$wert,"MQT",10);
        try {
          $MQTTDaten["MQTTPublishReturnCode"] = $client->publish($topic, $wert, 0, false);
        }
        catch(Mosquitto\Exception $e){
          Log::write($topic." rc: ".$e->getMessage(),"MQT",1);
        }
      }
    }
    Log::write("MQTT Daten zum Broker gesendet bzw. vom Broker empfangen. Adresse: ".$MQTTBroker." Port: ".$MQTTPort,"MQT",7);
    $aktuelleDaten = array();
    $PipeDaten = false;
  }
  $client->loop(5);

} while (($Startzeit + 58) > time());

fclose($fifo);



// $client->loop();
$client->disconnect();
unset($client);

Log::write("Ende der Verarbeitung. (MQTT)","MQT",8);
Log::write("MQTT Ende: ".var_export($MQTTDaten,1),"MQT",9);


exit;

function mqttDatenAuswerten($RawDaten) {
  global $funktionen;

  /*********************************************
    $RawDaten["MQTTMessageReturnText"] =  RX-OK
    $RawDaten["MQTTNachricht"] =  Wert
    $RawDaten["MQTTTopic"] = Topic
    $RawDaten["MQTTQos"] =  QoS
    $RawDaten["MQTTMid"] =  Message ID
    $RawDaten["MQTTRetain"] =  Retain 0 oder 1
  **********************************************/

  $Teile = explode("/",$RawDaten["MQTTTopic"],4);

  if (file_exists($basedir."/config/".$Teile[2].".user.config.php")) {
    Log::write("File ist vorhanden. ".$Teile[2].".user.config.php","   ",9);
    include($basedir."/config/".$Teile[2].".user.config.php");
    $Daten["GeraeteID"] = $Teile[2];
  }
  else   {
    include($basedir."/config/user.config.php");
  }

  
  if ($Teile[1] == "anzeige") {
      /************************************************************************
      //  Die Daten sollen in die Influx Datenbank geschrieben werden
      ************************************************************************/
      //echo "Anzeige\n";
      //echo $Teile[2]."\n";
      //echo $RawDaten["MQTTNachricht"]."\n";
      Log::write(print_r($Teile,1),"MQT",9);

      if ($InfluxDB_remote) {
        $Daten["InfluxAdresse"] = $InfluxAdresse;
        $Daten["InfluxPort"] =  $InfluxPort;
        $Daten["InfluxUser"] =  $InfluxUser;
        $Daten["InfluxPassword"] = $InfluxPassword;
        $Daten["InfluxDBName"] = $InfluxDBName;
        $Daten["InfluxSSL"] = $InfluxSSL;
      }
      else {
        $Daten["InfluxAdresse"] = "localhost";
        $Daten["InfluxPort"] =  "8086";
        $Daten["InfluxDBName"] = $InfluxDBLokal;
     }

      // $Daten["InfluxSpalte"] = $Teile[3]; geändert 5.2.2023
      $Daten["InfluxSpalte"] = str_replace("/","_",$Teile[3]);
      $Daten["InfluxWert"] = $RawDaten["MQTTNachricht"];
      $ret = MQTT_speichern($Daten);
      Log::write("Daten in die Influx Datenbank '".$InfluxDBLokal."' geschrieben. Gerätenummer: ".$Teile[2]." Spalte: ".$Teile[3]."  Wert: ".$RawDaten["MQTTNachricht"]." RC: ".$ret,"MQT",8);
    }
    elseif ($Teile[1] == "befehl") {
      /************************************************************************
      //  Die Daten sollen an einen Wechselrichter gesendet werden. Dazu werden
      //  sie in die Datei /var/www/pipe/befehl.steuerung  geschrieben.
      ************************************************************************/
      //echo "Befehl\n";
      //echo $Teile[2]."\n";
      //echo $Teile[3]."\n";
      //echo $RawDaten["MQTTNachricht"]."\n";
      Log::write("Befehl in die Datei '/var/www/pipe/".$Teile[2].".befehl.steuerung' geschrieben. ".$Teile[3].$RawDaten["MQTTNachricht"],"MQT",7);
      $l = 0;
      do {
        $fh = fopen("/var/www/pipe/".$Teile[2].".befehl.steuerung",'a');
        fwrite($fh,$Teile[3].$RawDaten["MQTTNachricht"]."\n");
        fclose($fh);
        $l++;
      } while ($fh === false and $l < 3);
    }

  return;

}

function MQTT_speichern($daten)  {


  $query  = "MQTT ";
  $query .= $daten['InfluxSpalte']."=\"".$daten['InfluxWert']."\"";

  $ch = curl_init('http://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s');

  $i = 1;
  do {

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 9);                //timeout in second s
    curl_setopt($ch, CURLOPT_PORT, $daten["InfluxPort"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    if (!empty($daten["InfluxUser"]) and !empty($daten["InfluxPassword"])) {
      curl_setopt($ch, CURLOPT_USERPWD, $daten["InfluxUser"].":".$daten["InfluxPassword"]);
    }
    if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $rc_info = curl_getinfo ($ch);
    $Ausgabe = json_decode($result,true);

    if (curl_errno($ch)) {
      $Meldung = "Curl Fehler! Daten nicht zur InfluxDB gesendet! Curl ErrNo. ".curl_errno($ch);
    }
    elseif ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
      $Meldung = "OK. Daten zur InfluxDB  gesendet.";
      break;
    }
    elseif(empty($Ausgabe["error"])) {
      $i++;
      continue;
    }
    else {
      $Meldung = "Daten nicht zur InfluxDB gesendet! info: ".print_r($rc_info,1);
    }
    $i++;
    sleep(2);
  } while ($i < 3);

  curl_close($ch);
  unset($ch);

  return $Meldung;
}


?>