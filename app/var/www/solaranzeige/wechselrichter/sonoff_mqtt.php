<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient als grafische Darstellung von MQTT Daten der Tasmota Module
//
// SonoffModul 1 = Sonoff basic   oder   Shelly Plug S
//             4 = Sonoff TH 10/16
//             6 = Sonoff POW R2
//            43 = Sonoff POW R2
//            46 = Shelly 1
//            53 = Jogis Delock
//            55 = Gosund SP1
//           200 = Shelly 2.5         DeviceName = "Shelly 2.5"
//           201 = Shelly 1PM         DeviceName = "Shelly 1PM"
//           201 = Shelly Plus 1PM    DeviceName = "Shelly Plus 1PM"
//           204 = Sonoff POW R3      DeviceName = "Sonoff POW R3"
//           201 = Gosund EP2         DeviceName = "Gosund EP2"
//           201 = Shelly Plug S      DeviceName = "Shelly Plug S"
//           201 = Shelly Plug S      DeviceName = "Shelly Plug S"
//           201 = Sonoff THR320D     DeviceName = "THR320D"
//
//
//
//
*****************************************************************************/
//  Das sind die default Werte des Mosquitto Brokers, der mit auf dem
//  Raspberry läuft. Hier nur etwas ändern, falls die Werte des Brokers
//  geändert wurden.
$MQTTBroker = "localhost";
$MQTTPort = 1883;
$MQTTKeepAlive = 60;
//
//***************************************************************************/
error_reporting( E_ERROR | E_WARNING | E_PARSE | E_STRICT );

$Go = true;
$Tracelevel = 7; //  1 bis 10  10 = Debug
$aktuelleDaten = array();
$aktuelleDaten["Status"] = "Offline";
$aktuelleDaten["Period"] = "0";
$aktuelleDaten["Powerstatus"] = "0";
$aktuelleDaten["Temperatur"] = "0";
$aktuelleDaten["Powerstatus0"] = "0";
$aktuelleDaten["Powerstatus1"] = "0";
$aktuelleDaten["Sensor"] = "0";
$Startzeit = time( ); // Timestamp festhalten
$RemoteDaten = true;
$Version = "";
Log::write( "-------------   Start  sonoff_mqtt.php    --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 6 );
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
Log::write( "Hardware Version: ".$Version, "o  ", 8 );
switch ($Version) {

  case "2B":
    break;

  case "3B":
    break;

  case "3BPlus":
    break;

  case "4B":
    break;

  default:
    break;
}
$MQTTDaten = array();
$client = new Mosquitto\Client( );
$client->onConnect( [$funktionen, 'mqtt_connect'] );
$client->onDisconnect( [$funktionen, 'mqtt_disconnect'] );
$client->onPublish( [$funktionen, 'mqtt_publish'] );
$client->onSubscribe( [$funktionen, 'mqtt_subscribe'] );
$client->onMessage( [$funktionen, 'mqtt_message'] );
if (!empty($MQTTBenutzer) and !empty($MQTTKennwort)) {
  $client->setCredentials( $MQTTBenutzer, $MQTTKennwort );
}
if ($MQTTSSL) {
  $client->setTlsCertificates( $basedir."/ca.crt" );
  $client->setTlsInsecure( SSL_VERIFY_NONE );
}
$rc = $client->connect( $MQTTBroker, $MQTTPort, $MQTTKeepAlive );
for ($i = 1; $i < 200; $i++) {
  // Warten bis der connect erfolgt ist.
  if (empty($MQTTDaten)) {
    $client->loop( 100 );
  }
  else {
    break;
  }
}
$client->subscribe( "+/".$Topic."/#", 0 ); // Subscribe
$client->subscribe( $Topic."/#", 0 ); // Subscribe
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 3 );
  $Startzeit2 = time( ); // Timestamp festhalten

  /****************************************************************************
  //  Ab hier wird das Sonoff Gerät ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["Datum"]                   Text
  //  $aktuelleDaten["AC_Spannung"]
  //  $aktuelleDaten["AC_Strom"]
  //  $aktuelleDaten["AC_Leistung"]
  //  $aktuelleDaten["AC_Scheinleistung"]
  //  $aktuelleDaten["AC_Wirkleistung"]
  //  $aktuelleDaten["Status"]                  Text
  //  $aktuelleDaten["Powerstatus"]             0 / 1
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamtGestern"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //
  ****************************************************************************/
  do {
    $aktuelleDaten["AC_Frequenz"] = 0;
    $client->loop( 100 );
    if ((isset($MQTTDaten["MQTTMessageReturnText"]) and $MQTTDaten["MQTTMessageReturnText"] == "RX-OK") or isset($TopicTeile)) {
      Log::write( $MQTTDaten["MQTTMessageReturnText"], "MQT", 10 );

      /*************************************************************************
      //  MQTT Meldungen empfangen. Subscribing    Subscribing    Subscribing
      //  Hier werden die Daten vom Mosquitto Broker gelesen.
      *************************************************************************/
      Log::write( print_r( $MQTTDaten, 1 ), "MQT", 10 );
      Log::write( "Nachricht: ".$MQTTDaten["MQTTNachricht"], "MQT", 9 );
      Log::write( "Topic: ".$MQTTDaten["MQTTTopic"], "MQT", 8 );
      if (strtoupper( $MQTTDaten["MQTTNachricht"] ) == "ONLINE") {
        // Gerät ist online
        $aktuelleDaten["Status"] = $MQTTDaten["MQTTNachricht"];
        Log::write( "Topic: ".$MQTTDaten["MQTTTopic"], "   ", 8 );
        $TopicTeile = explode( "/", $MQTTDaten["MQTTTopic"] );
        if ($TopicTeile[0] == "tele") {
          $Prefix = 0;
          $TopicPosition = 1;
          $Payload = 2;
        }
        elseif ($TopicTeile[1] == "tele") {
          $Prefix = 1;
          $TopicPosition = 0;
          $Payload = 2;
        }
      }
      Log::write( "Prefix: ".$Prefix." TopicPosition: ".$TopicPosition." Payload: ".$Payload, "MQT", 9 );
      $values = json_decode( $MQTTDaten["MQTTNachricht"], true );
      Log::write( print_r( $values, 1 ), "+  ", 10 );
      if (is_array( $values )) {
        if (isset($values["StatusSNS"])) {
          Log::write( "StatusSNS Topic".print_r( $values, 1 ), "   ", 8 );
          $aktuelleDaten["WattstundenGesamt"] = 0;
          $aktuelleDaten["WattstundenGesamtHeute"] = 0;
          $aktuelleDaten["WattstundenGesamtGestern"] = 0;
          $aktuelleDaten["AC_Leistung"] = 0;
          foreach ($values["StatusSNS"] as $k => $v) {
            $inputs[] = array("name" => $k, "value" => $v);
            Log::write( print_r( $inputs, 1 ), "   ", 10 );
            if ($k == "Epoch") {
              // Die richtige Time Zone muss am Sonoff eingestellt sein.
              $aktuelleDaten["Timestamp"] = $v;
            }
            if ($k == "ENERGY") {
              if (isset($v["Voltage"])) {
                // Faktor =  Leistung / Scheinleistung!
                $aktuelleDaten["AC_Spannung"] = $v["Voltage"];
                $aktuelleDaten["TotalStartTime"] = $v["TotalStartTime"];
                $aktuelleDaten["WattstundenGesamt"] = ($v["Total"] * 1000);
                $aktuelleDaten["WattstundenGesamtHeute"] = ($v["Today"] * 1000);
                $aktuelleDaten["WattstundenGesamtGestern"] = ($v["Yesterday"] * 1000);
                if (is_array( $v["Current"] ) and count( $v["Current"] ) > 1) {
                  Log::write( "Anzahl: ".count( $v["Current"] ), "   ", 9 );
                  $aktuelleDaten["AC_Frequenz"] = $v["Frequency"];
                  for ($l = 0; $l < count( $v["Current"] ); $l++) {
                    $aktuelleDaten["AC_Strom".$l] = $v["Current"][$l];
                    $Faktor = $v["Factor".$l][$l];
                    $aktuelleDaten["AC_Leistung".$l] = $v["Power"][$l];
                    $aktuelleDaten["AC_Scheinleistung".$l] = $v["ApparentPower"][$l];
                    $aktuelleDaten["AC_Blindleistung".$l] = $v["ReactivePower"][$l];
                  }
                }
                else {
                  $aktuelleDaten["AC_Strom"] = $v["Current"];
                  $Faktor = $v["Factor"];
                  $aktuelleDaten["AC_Leistung"] = $v["Power"];
                  $aktuelleDaten["AC_Scheinleistung"] = $v["ApparentPower"];
                  $aktuelleDaten["AC_Blindleistung"] = $v["ReactivePower"];
                }
              }
              if (is_array( $v )) {
                $aktuelleDaten["TotalStartTime"] = $v["TotalStartTime"];
                $aktuelleDaten["WattstundenGesamt"] = ($v["Total"] * 1000);
                $aktuelleDaten["WattstundenGesamtHeute"] = ($v["Today"] * 1000);
                $aktuelleDaten["WattstundenGesamtGestern"] = ($v["Yesterday"] * 1000);
                $aktuelleDaten["AC_Leistung"] = $v["Power"];
              }
            }
            if ($k == "ESP32") {
              if (isset($v["Temperature"])) {
                // Temperatur
                $aktuelleDaten["Temperatur"] = $v["Temperature"];
              }
            }
            if ($k == "SI7021") { //TH16 Sonoff
              $aktuelleDaten["Temperatur"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = $v["Humidity"];
              $aktuelleDaten["Taupunkt"] = $v["DewPoint"];
              $aktuelleDaten["Sensor"] = "SI7021";
              if ($aktuelleDaten["Luftfeuchte"] < 0) {
                $aktuelleDaten["Luftfeuchte"] = 100;
              }
            }
            if ($k == "DS18B20") { //TH16 Sonoff
              $aktuelleDaten["Temperatur"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = 0;
              $aktuelleDaten["Sensor"] = "DS18B20";
            }
            if ($k == "DS18B20-1") { //Shelly
              $aktuelleDaten["Temperatur-1"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = 0;
              $aktuelleDaten["Sensor"] = "DS18B20";
            }
            if ($k == "DS18B20-2") { //Shelly
              $aktuelleDaten["Temperatur-2"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = 0;
              $aktuelleDaten["Sensor"] = "DS18B20";
            }
            if ($k == "DS18B20-3") { //Shelly
              $aktuelleDaten["Temperatur-3"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = 0;
              $aktuelleDaten["Sensor"] = "DS18B20";
            }
            if ($k == "AM2301") { //TH10
              $aktuelleDaten["Temperatur"] = $v["Temperature"];
              $aktuelleDaten["Luftfeuchte"] = $v["Humidity"];
              $aktuelleDaten["Taupunkt"] = $v["DewPoint"];
              $aktuelleDaten["Sensor"] = "AM2301";
            }
            if ($k == "TempUnit") {
              $aktuelleDaten["Masseinheit"] = $v;
            }
            if ($k == "ANALOG") { //Shelly 2.5
              $aktuelleDaten["Temperatur"] = $v["Temperature"];
              if (isset($v["Temperature1"])) {
                $aktuelleDaten["Temperatur1"] = $v["Temperature1"];
              }
            }
            if ($k == "MT175" or $k == "MT681") { //Zähler LED
              $aktuelleDaten["Energie_in"] = ($v["E_in"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["E_out"] * 1000);
              $aktuelleDaten["Leistung"] = $v["P"];
              $aktuelleDaten["L1_Watt"] = $v["L1"];
              $aktuelleDaten["L2_Watt"] = $v["L2"];
              $aktuelleDaten["L3_Watt"] = $v["L3"];
              $aktuelleDaten["Server_ID"] = $v["Server_ID"];
              $aktuelleDaten["Sensor"] = "MT175";
              $aktuelleDaten["Sensor"] = $k;
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Leistung"] = $v["P"];
              }
            }
            if ($k == "MT631") { //Zähler LED
              $aktuelleDaten["Energie_in"] = ($v["E_in"] * 1000);
              $aktuelleDaten["Leistung"] = $v["P"];
              $aktuelleDaten["Sensor"] = "MT631";
            }
            if ($k == "mMe4") { //Zähler LED
              $aktuelleDaten["Energie_in"] = ($v["E_in"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["E_out"] * 1000);
              $aktuelleDaten["Leistung"] = ($v["Power"]);
              $aktuelleDaten["Sensor"] = "mMe4";
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Leistung"] = $v["Power"];
              }
            }
            if ($k == "LK13BE") { //Zähler LED
              $aktuelleDaten["Energie_in"] = ($v["total_in"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["total_out"] * 1000);
              $aktuelleDaten["Energie_in1d"] = ($v["total_1d"] * 1000);
              $aktuelleDaten["Energie_in7d"] = ($v["total_7d"] * 1000);
              $aktuelleDaten["Energie_in30d"] = ($v["total_30d"] * 1000);
              $aktuelleDaten["Energie_in365d"] = ($v["total_365d"] * 1000);
              $aktuelleDaten["Leistung"] = ($v["Power"]);
              $aktuelleDaten["Sensor"] = "LK13BE";
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Leistung"] = $v["Power"];
              }
            }
            if ($k == "DD32R06") { //Zähler LED
              $aktuelleDaten["Energie_inGesamt"] = ($v["1_8_0"] * 1000);
              $aktuelleDaten["Energie_inT1"] = ($v["1_8_1"] * 1000);
              $aktuelleDaten["Energie_inT2"] = ($v["1_8_2"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["2_8_0"] * 1000);
              $aktuelleDaten["Leistung"] = ($v["16_7_0"]);
              $aktuelleDaten["Leistung_R"] = ($v["36_7_0"]);
              $aktuelleDaten["Leistung_S"] = ($v["56_7_0"]);
              $aktuelleDaten["Leistung_T"] = ($v["76_7_0"]);
              $aktuelleDaten["Sensor"] = "DD32R06";
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
              }
            }
            if ($k == "Q3A") { //Zähler LED
              $aktuelleDaten["Energie_inGesamt"] = ($v["Verbrauch_Summe"] * 1000);
              $aktuelleDaten["Energie_inT1"] = ($v["Verbrauch_T1"] * 1000);
              $aktuelleDaten["Energie_inT2"] = ($v["Verbrauch_T2"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["Einspeisung_Summe"] * 1000);
              $aktuelleDaten["Leistung"] = ($v["Watt_Summe"]);
              $aktuelleDaten["Leistung_R"] = ($v["Watt_L1"]);
              $aktuelleDaten["Leistung_S"] = ($v["Watt_L2"]);
              $aktuelleDaten["Leistung_T"] = ($v["Watt_L3"]);
              $aktuelleDaten["Sensor"] = "Q3A";
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
                if (isset($v["Power"])) {
                  $aktuelleDaten["Leistung"] = $v["Power"];
                }
              }
            }
            if ($k == "EHZ541" or $k == "LK13SML") { //Zähler LED Holley EHZ541 + LK13 SML
              $aktuelleDaten["Energie_in"] = ($v["total_in"] * 1000);
              $aktuelleDaten["Energie_out"] = ($v["total_out"] * 1000);
              $aktuelleDaten["Sensor"] = $k;
              $aktuelleDaten["Leistung"] = ($v["Power"]);
              if ($aktuelleDaten["Leistung"] >= 0) {
                $aktuelleDaten["Bezug"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Einspeisung"] = 0;
              }
              else {
                $aktuelleDaten["Bezug"] = 0;
                $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Leistung"];
                $aktuelleDaten["Leistung"] = $v["Power"];
              }
              $aktuelleDaten["Volt_R"] = ($v["Volt_L1"]);
              $aktuelleDaten["Volt_S"] = ($v["Volt_L2"]);
              $aktuelleDaten["Volt_T"] = ($v["Volt_L3"]);
              $aktuelleDaten["Ampere_R"] = ($v["Ampere_L1"]);
              $aktuelleDaten["Ampere_S"] = ($v["Ampere_L2"]);
              $aktuelleDaten["Ampere_T"] = ($v["Ampere_L3"]);
              $aktuelleDaten["Phasenwinkel_R"] = ($v["Phasenwinkel_L1"]);
              $aktuelleDaten["Phasenwinkel_S"] = ($v["Phasenwinkel_L2"]);
              $aktuelleDaten["Phasenwinkel_T"] = ($v["Phasenwinkel_L3"]);
              $aktuelleDaten["Frequenz"] = ($v["Frequenz"]);
              if ($aktuelleDaten["Sensor"] == "LK13SML") {
                $aktuelleDaten["Leistung_R"] = ($v["Power_L1"]);
                $aktuelleDaten["Leistung_S"] = ($v["Power_L2"]);
                $aktuelleDaten["Leistung_T"] = ($v["Power_L3"]);
              }
            }
            if ($k == "WAERME") { //Zähler LED WAERME
              $aktuelleDaten["Energie"] = ($v["w_total"] * 1000);
              $aktuelleDaten["Volumen"] = ($v["v_total"]);
              $aktuelleDaten["Leistung"] = ($v["p_act"]);
              $aktuelleDaten["Durchfluss"] = "F_akt";
              $aktuelleDaten["Temperatur"] = ($v["t_flow"]);
              $aktuelleDaten["Ruecklauf_Temp"] = ($v["t_return"]);
              $aktuelleDaten["Temp_differenz"] = ($v["t_diff"]);
              $aktuelleDaten["Sensor"] = "WAERME";
            }
          }
        }
        if (isset($values["StatusSTS"])) {
          foreach ($values["StatusSTS"] as $k => $v) {
            $inputs[] = array("name" => $k, "value" => $v);
            if ($k == "POWER") {
              if ($v == "ON") {
                $aktuelleDaten["Powerstatus"] = "1";
              }
              else {
                $aktuelleDaten["Powerstatus"] = "0";
              }
            }
            elseif ($k == "POWER1") {
              if ($v == "ON") {
                $aktuelleDaten["Powerstatus0"] = "1";
                $aktuelleDaten["Powerstatus"] = "1";
              }
              else {
                $aktuelleDaten["Powerstatus0"] = "0";
                $aktuelleDaten["Powerstatus"] = "0";
              }
            }
            elseif ($k == "POWER2") {
              if ($v == "ON") {
                $aktuelleDaten["Powerstatus1"] = "1";
              }
              else {
                $aktuelleDaten["Powerstatus1"] = "0";
              }
            }
          }
          Log::write( "Powerstatus: ".$aktuelleDaten["Powerstatus"], "*- ", 10 );
          break;
        }
        if (isset($values["StatusFWR"])) {
          foreach ($values["StatusFWR"] as $k => $v) {
            $inputs[] = array("name" => $k, "value" => $v);
            if ($k == "Version") {
              $aktuelleDaten["Produkt"] = $v;
            }
            if ($k == "Hardware") {
              $aktuelleDaten["Hardware"] = $v;
            }
          }
        }
        if (isset($values["Status"])) {
          foreach ($values["Status"] as $k => $v) {
            $inputs[] = array("name" => $k, "value" => $v);
            if ($k == "Module") {
              $aktuelleDaten["SonoffModul"] = $v;
            }
            if ($k == "DeviceName") {
              $aktuelleDaten["DeviceName"] = $v;
            }
          }
        }
        if (isset($values["StatusNET"])) {
          foreach ($values["StatusNET"] as $k => $v) {
            $inputs[] = array("name" => $k, "value" => $v);
            if ($k == "Hostname") {
              $aktuelleDaten["Hostname"] = $v;
            }
          }
        }
      }
      $MQTTDaten["MQTTMessageReturnText"] = "RX-NO";
      if ($Go) {
        if ($TopicPosition == 0) {
          $topic = $Topic."/cmnd/status";
        }
        else {
          $topic = "cmnd/".$Topic."/status";
        }
        $wert = "0";
        try {
          $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $topic, $wert, 0, false );
          Log::write( "Befehl gesendet: ".$topic." Wert: ".$wert, "MQT", 8 );
        }
        catch (Mosquitto\Exception $e) {
          Log::write( $topic." rc: ".$e->getMessage( ), "MQT", 1 );
        }
        $Go = false;
      }
    }
  } while (($Startzeit2 + 6) > time( ));

  /****************************************************************************
  //  ENDE SONOFF MODUL AUSLESEN         ENDE SONOFF MODUL AUSLESEN
  //
  //  Ändern des Device Namen:            (Leerstelle = %20)
  //  curl "http://<IP Relais>/cm?cmnd=DeviceName%20Shelly%201"
  ****************************************************************************/
  if (!isset($aktuelleDaten["SonoffModul"])) {
    Log::write( "Keine Daten vom Sonoff Modul empfangen.", "   ", 6 );
    goto Ausgang;
  }
  Log::write( "SonoffModul: ".$aktuelleDaten["SonoffModul"], "   ", 8 );
  Log::write( "Firmware: ".$aktuelleDaten["Produkt"], "   ", 8 );
  switch ($aktuelleDaten["SonoffModul"]) {

    case 0:
      if (strtoupper( $aktuelleDaten["DeviceName"] ) == "GOSUND EP2") {
        // Es handelt sich um einen Gosund EP2
        Log::write( "Es handelt sich um ein Gosund EP2,  Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif ($aktuelleDaten["DeviceName"] == "shelly" or strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY 2.5" or strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY_2.5") {
        // Es handelt sich um einen Shelly 2.5
        Log::write( "Es handelt sich um ein Shelly 2.5 Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 200; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY 1PM") {
        // Es handelt sich um einen Shelly 2.5 : Eine Phase
        Log::write( "Es handelt sich um ein Shelly 1PM  Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY1" or strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY 1") {
        // Es handelt sich um einen Shelly 1 :  Eine Phase
        Log::write( "Es handelt sich um ein Shelly 1 Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "SONOFF_POW_R3" or strtoupper( $aktuelleDaten["DeviceName"] ) == "SONOFF POW R3") {
        // Es handelt sich um einen Sonoff POW R3
        Log::write( "Es handelt sich um ein Sonoff POW R3 Modul, Hardware: ".$aktuelleDaten["Hardware"].",  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201;
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY PLUS 1PM") {
        // Es handelt sich um einen Shelly 2.5 : Eine Phase
        Log::write( "Es handelt sich um ein Shelly Plus 1PM  Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "SHELLY PLUG S") {
        // Es handelt sich um einen Shelly Plug S: Eine Phase
        Log::write( "Es handelt sich um ein Shelly Plug S  Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "THR320D") {
        // Es handelt sich um einen Sonoff THR320D: Eine Phase
        Log::write( "Es handelt sich um ein Sonoff THR320D Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 201; // Dummy Nummer
      }
      elseif (strtoupper( $aktuelleDaten["DeviceName"] ) == "GOSUND") {
        // Es handelt sich um einen Gosund EP2
        Log::write( "Es handelt sich um eine Gosund Steckdose,  Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 55; // Dummy Nummer
      }
      elseif ($aktuelleDaten["Hardware"] == "ESP8266EX") {
        // Es handelt sich um einen Sonoff POW R3
        Log::write( "Es handelt sich um ein Sonoff POW R3 Modul, Hardware: ".$aktuelleDaten["Hardware"].",  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
        $aktuelleDaten["SonoffModul"] = 43;
      }
      else {
        Log::write( "SonoffModul: ".$aktuelleDaten["SonoffModul"], "   ", 7 );
        Log::write( "Firmware: ".$aktuelleDaten["Produkt"], "   ", 7 );
        Log::write( "Das Relais ist ein unbekanntes Tasmota Modul. Bitte melden: support@solaranzeige.de", "   ", 5 );
      }
      break;

    case 1:
      // Es handelt sich um einen Sonoff Basic
      Log::write( "Es handelt sich um ein Sonoff Basic Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      if ($aktuelleDaten["Sensor"] == "MT175" or $aktuelleDaten["Sensor"] == "MT681") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 300;
      }
      if ($aktuelleDaten["Sensor"] == "MT631") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 301;
      }
      if ($aktuelleDaten["Sensor"] == "mMe4") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 302;
      }
      if ($aktuelleDaten["Sensor"] == "LK13BE") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 303;
      }
      if ($aktuelleDaten["Sensor"] == "DD32R06") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 304;
      }
      if ($aktuelleDaten["Sensor"] == "Q3A") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 305;
      }
      if ($aktuelleDaten["Sensor"] == "EHZ541" or $aktuelleDaten["Sensor"] == "LK13SML") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 306;
      }
      if ($aktuelleDaten["Sensor"] == "WAERME") { // IR-Lesekopf
        // Es handelt sich um einen IR-Lesekopf
        $aktuelleDaten["SonoffModul"] = 307;
      }
      break;

    case 4:
      // Es handelt sich um einen Sonoff TH10 oder TH16
      Log::write( "Es handelt sich um ein Sonoff TH10 / TH16 Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      break;

    case 6:
      // Es handelt sich um einen Sonoff POW (6)
      Log::write( "Es handelt sich um ein Sonoff POW Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );

    case 43:
      // Es handelt sich um einen Sonoff POW R2
      Log::write( "Es handelt sich um ein Sonoff POW R2 Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      break;

    case 46:
      // Es handelt sich nicht um einen Shelly 1 mit Temperatursensoren
      Log::write( "Es handelt sich um ein Shelly 1 mit Temperatursensoren: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      break;

    case 53:
      // Es handelt sich um Jogis Delock 11827 Modul (APLIC WDP303075)
      $aktuelleDaten["Produkt"] = "Jogis Delock";
      Log::write( "Es handelt sich um ein Tasmota Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      break;

    case 55:
      // Es handelt sich um Gosund SP1 Modul
      $aktuelleDaten["Produkt"] = "GOSUND SP1";
      Log::write( "Es handelt sich um ein GOSUND SP1 Modul Nr.: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      break;

    default:
      Log::write( "Das Sonoff Relais ist nicht aktiv oder es ist kein unterstütztes Sonoff Gerät. Tasmota Modul: ".$aktuelleDaten["SonoffModul"]."  Firmware: ".$aktuelleDaten["Produkt"], "   ", 5 );
      goto Ausgang;
      break;
  }

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = 0;
  //  Dummy für HomeMatic
  if (isset($aktuelleDaten["AC_Spannung"])) {
    $aktuelleDaten["AC_Ausgangsspannung"] = $aktuelleDaten["AC_Spannung"];
  }
  elseif ($aktuelleDaten["SonoffModul"] == 201) {
    // Dummy
  }
  else {
    // Dummy für Statistik
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/sonoff_mqtt_math.php" )) {
    include $basedir.'/custom/sonoff_mqtt_math.php'; // Falls etwas neu berechnet werden muss.
  }
  Log::write( print_r( $aktuelleDaten, 1 ), "*- ", 8 );

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] = $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;

  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    // Test ob die Remote Verbindung zur Verfügung steht.
    if ($RemoteDaten) {
      $rc = InfluxDB::influx_remote_test( );
      if ($rc) {
        $rc = InfluxDB::influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = InfluxDB::influx_local( $aktuelleDaten );
  }
  if (is_file( $basedir."/config/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time( ) - $Startzeit));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (54 - (time( ) - $Startzeit)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (54 - (time( ) - $Startzeit)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "OK. Daten gelesen.", "   ", 9 );
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    $MQTTDaten = array();
    break;
  }
  $Go = true;
  $i++;
  $MQTTDaten = array();
} while (($Startzeit + 54) > time( ));

/*********/
Ausgang:

/********/
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    // $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    Log::write( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write( "Nachrichten versenden...", "   ", 8 );
    require($basedir."/services/meldungen_senden.php");
  }
  Log::write( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  Log::write( "Keine gültigen Daten empfangen.", "!! ", 6 );
}
Log::write( "-------------   Stop   sonoff_mqtt.php     -------------------- ", "|--", 6 );
unset($TopicTeile);
$rc = $client->disconnect( );
return;
?>
