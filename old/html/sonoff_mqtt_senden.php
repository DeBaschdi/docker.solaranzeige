<?php
/******************************************************************************
//  Hiermit werden Befehle an Sonoff Geräte gesendet. Dieser Script
//  wird über http aufgerufen mit 2 Parameter:
//  http://solaranzeige.local/sonoff_mqtt_senden.php?
//  topic=cmnd/Sonoff/teleperiod&wert=30
//
******************************************************************************/
//  Das sind die default Werte des Mosquitto Brokers, der mit auf dem 
//  Raspberry läuft. Hier nur etwas ändern, falls die Werte des Brokers 
//  geändert wurde.
$MQTTBroker = "localhost";
$MQTTPort = 1883;
$MQTTKeepAlive = 60;
//
//***************************************************************************/
require("phpinc/funktionen.inc.php");
$Tracelevel = 10; //  1 bis 10  10 = Debug
$funktionen = new funktionen();

if (isset($_GET['topic'])) {
  $topic = strtolower($_GET['topic']);
  if (isset($_GET['wert'])) {
    $wert = $_GET['wert'];
  }
  else {
    $wert = "";
  }
}


$funktionen->log_schreiben("Der Befehl ".$Befehl." ".$Wert." wird an ein Sonoff Gerät gesendet.","   ",5);


$MQTTDaten = array();


$client = new Mosquitto\Client();
$client->onConnect([$funktionen,'mqtt_connect']);
$client->onDisconnect([$funktionen, 'mqtt_disconnect']);
$client->onPublish([$funktionen,'mqtt_publish']);
$client->onSubscribe([$funktionen,'mqtt_subscribe']);
$client->onMessage([$funktionen,'mqtt_message']);

if (!empty($MQTTBenutzer) and !empty($MQTTKennwort)) {
  $client->setCredentials($MQTTBenutzer, $MQTTKennwort);
}
if ($MQTTSSL) {
  $client->setTlsCertificates($Pfad."/ca.cert");
  $client->setTlsInsecure(SSL_VERIFY_NONE);
}

$rc = $client->connect($MQTTBroker, $MQTTPort, $MQTTKeepAlive);
for ($i=1;$i<200;$i++) {
  // Warten bis der connect erfolgt ist.
  if (empty($MQTTDaten)) {
    $client->loop(100);
  }
  else {
    break;
  }
}


$funktionen->log_schreiben($MQTTDaten["MQTTConnectReturnText"],"MQT",8);



$funktionen->log_schreiben($topic." -> ".$wert,"MQT",9);
try {
  $MQTTDaten["MQTTPublishReturnCode"] = $client->publish($topic, $wert, 0, false);
}
catch(Mosquitto\Exception $e){
  $funktionen->log_schreiben($topic." rc: ".$e->getMessage(),"MQT",1);
}


header("Location: localhost:3000");


exit;
?>
