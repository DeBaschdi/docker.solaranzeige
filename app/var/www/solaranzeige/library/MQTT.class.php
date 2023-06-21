<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class MQTT {

  /**************************************************************************
  //  MQTT Fuktionen zum senden der mqtt Messages
  //  Call back functions for MQTT library
  ***************************************************************/
  public static function mqtt_connect($r) {
    global $MQTTDaten;
    $MQTTDaten["MQTTConnectReturnCode"] = $r;
    if ($r == 0)
      $MQTTDaten["MQTTConnectReturnText"] = "{$r}-CONX-OK|";
    if ($r == 1)
      $MQTTDaten["MQTTConnectReturnText"] = "{$r}-Connection refused (unacceptable protocol version)|";
    if ($r == 2)
      $MQTTDaten["MQTTConnectReturnText"] = "{$r}-Connection refused (identifier rejected)|";
    if ($r == 3)
      $MQTTDaten["MQTTConnectReturnText"] = "{$r}-Connection refused (broker unavailable )|";
  }

  public static function mqtt_publish() {
    global $MQTTDaten;
    $MQTTDaten["MQTTPublishReturnText"] = "Message published";
  }

  public static function mqtt_disconnect() {
    global $MQTTDaten;
    $MQTTDaten["MQTTDisconnectReturnText"] = "Disconnected";
  }

  public static function mqtt_subscribe() {
    global $MQTTDaten;
    //**Store the status to a global variable - debug purposes
    // $GLOBALS['statusmsg'] = $GLOBALS['statusmsg'] . "SUB-OK|";
    $MQTTDaten["MQTTSubscribeReturnText"] = "SUB-OK|";
  }

  public static function mqtt_message($message) {
    global $MQTTDaten;
    $MQTTDaten["MQTTRetain"] = 0;
    //**Store the status to a global variable - debug purposes
    // $GLOBALS['statusmsg']  = "RX-OK|";
    //**Store the received message to a global variable
    $MQTTDaten["MQTTMessageReturnText"] = "RX-OK";
    $MQTTDaten["MQTTNachricht"] = $message->payload;
    $MQTTDaten["MQTTTopic"] = $message->topic;
    $MQTTDaten["MQTTQos"] = $message->qos;
    $MQTTDaten["MQTTMid"] = $message->mid;
    $MQTTDaten["MQTTRetain"] = $message->retain;
  }
}
?>