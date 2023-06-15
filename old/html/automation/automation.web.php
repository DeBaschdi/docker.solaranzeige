<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient der Steuerung von Wärmepumpen und Heizungselementen
//  In der SQLite3 Datenbank "automation.sqlite3" und "datenauswahl.sqlite3"
//  sind die nötigen Parameter. Dieser Script wird von einer WEB-Seite
//  aufgerufen.
//
//
//
*****************************************************************************/
// $path_parts = pathinfo($argv[0]);
// $Pfad = $path_parts['dirname'];
$Datenbankname = "database/automation.sqlite3";

/*****************************************************************************
//  Tracelevel:
//  0 = keine LOG Meldungen
//  1 = Nur Fehlermeldungen
//  2 = Fehlermeldungen und Warnungen
//  3 = Fehlermeldungen, Warnungen und Informationen
//  4 = Debugging
*****************************************************************************/
$Tracelevel = 3;
$submit = false;
$Brokermeldung = "";
$MQTTDaten = array();
$DeviceName = "Offline";
$Power1 = 0;
$Power2 = 0;
$Power3 = 0;
$Power4 = 0;
$TopicPosition = 1;
$Fehlermeldung = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $submit = true;
  // collect value of input field
  // print_r($_POST);
}
log_schreiben( "- - - - - Start Web - Seite automation.web.php  - - - - - -", "|-->", 1 );

/****************************************************************************
//  SQLite Datenbank starten
//  und die Basiswerte auslesen
****************************************************************************/
if ($submit == true) {
  try {
    $Datenbankname = "database/datenauswahl.sqlite3";
    $db1 = db_connect( $Datenbankname );
    $db1->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  }
  catch (PDOException $e) {
  // Print PDOException message
    log_schreiben( $e->getMessage( ), "", 1 );
  }
}

/****************************************************************************
//  SQLite Datenbank starten
//  und die Steuerungsvariablen auslesen.
****************************************************************************/
try {
  $Datenbankname = "database/automation.sqlite3";
  $db2 = db_connect( $Datenbankname );
  $db2->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
}
catch (PDOException $e) {
// Print PDOException message
  log_schreiben( $e->getMessage( ), "", 1 );
}
// $varBasiswerte  ($db1)
if ($submit) {
  foreach ($_POST as $key => $wert) {
    if ($wert == " " or $wert == "") {
      $_POST[$key] = "null";
    }
  }
  // Submit Button ist gedrückt worden!
  // Werte in die $db2 = automation.sqlite3  schreiben
  $sql = "Update config set ";
  $sql .= "LRReglerNr = ".$_POST["LR_ReglerNr"];
  $sql .= ",WRReglerNr = ".$_POST["WR_ReglerNr"];
  $sql .= ",SMReglerNr = ".$_POST["SM_ReglerNr"];
  $sql .= ",BMSReglerNr = ".$_POST["BMS_ReglerNr"];
  $sql .= ",LadereglerID = ".$_POST["LadereglerID"];
  $sql .= ",WechselrichterID = ".$_POST["WechselrichterID"];
  $sql .= ",SmartMeterID = ".$_POST["SmartMeterID"];
  $sql .= ",BMSID = ".$_POST["BMSID"];
  $sql .= ",LRDB = \"".$_POST["LR_Datenbank"]."\"";
  $sql .= ",WRDB = \"".$_POST["WR_Datenbank"]."\"";
  $sql .= ",SMDB = \"".$_POST["SM_Datenbank"]."\"";
  $sql .= ",BMSDB = \"".$_POST["BMS_Datenbank"]."\"";
  $sql .= ",Relais1Typ = \"".$_POST["Relais1Typ"]."\"";
  $sql .= ",Relais1AnzKontakte = ".$_POST["Relais1AnzKontakte"];
  $sql .= ",Relais1Topic = \"".$_POST["Relais1Topic"]."\"";
  $sql .= ",Relais2Typ = \"".$_POST["Relais2Typ"]."\"";
  $sql .= ",Relais2AnzKontakte = ".$_POST["Relais2AnzKontakte"];
  $sql .= ",Relais2Topic = \"".$_POST["Relais2Topic"]."\"";
  if (isset($_POST["Relais1aktiv"]))
    $sql .= ",Relais1aktiv = 1";
  else
    $sql .= ",Relais1aktiv = 0";
  $sql .= ",Relais1K1PVein = ".$_POST["11PVWattein"];
  $sql .= ",Relais1K1ACein = ".$_POST["11ACWattein"];
  $sql .= ",Relais1K1SMein = ".$_POST["11SMWattein"];
  $sql .= ",Relais1K1BMSein = ".$_POST["11BMSWattein"];
  $sql .= ",Relais1K1PVBedingungein = \"".$_POST["11PVein"]."\"";
  $sql .= ",Relais1K1ACBedingungein = \"".$_POST["11ACein"]."\"";
  $sql .= ",Relais1K1SMBedingungein = \"".$_POST["11SMein"]."\"";
  $sql .= ",Relais1K1PVaus = ".$_POST["11PVWattaus"];
  $sql .= ",Relais1K1ACaus = ".$_POST["11ACWattaus"];
  $sql .= ",Relais1K1SMaus = ".$_POST["11SMWattaus"];
  $sql .= ",Relais1K1BMSaus = ".$_POST["11BMSWattaus"];
  $sql .= ",Relais1K1PVBedingungaus = \"".$_POST["11PVaus"]."\"";
  $sql .= ",Relais1K1ACBedingungaus = \"".$_POST["11ACaus"]."\"";
  $sql .= ",Relais1K1SMBedingungaus = \"".$_POST["11SMaus"]."\"";
  $sql .= ",Relais1K1einMinuten = \"".$_POST["11MinEin"]."\"";
  $sql .= ",Relais1K1ausMinuten = \"".$_POST["11MaxEin"]."\"";
  if (isset($_POST["Relais2aktiv"]))
    $sql .= ",Relais2aktiv = 1";
  else
    $sql .= ",Relais2aktiv = 0";
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
  log_schreiben( $sql, "", 4 );
  if (isset($_POST["12PVWattein"])) {
    $sql = "Update config set ";
    $sql .= "Relais1K2PVein = ".$_POST["12PVWattein"];
    $sql .= ",Relais1K2ACein = ".$_POST["12ACWattein"];
    $sql .= ",Relais1K2SMein = ".$_POST["12SMWattein"];
    $sql .= ",Relais1K2BMSein = ".$_POST["12BMSWattein"];
    $sql .= ",Relais1K2PVBedingungein = \"".$_POST["12PVein"]."\"";
    $sql .= ",Relais1K2ACBedingungein = \"".$_POST["12ACein"]."\"";
    $sql .= ",Relais1K2SMBedingungein = \"".$_POST["12SMein"]."\"";
    $sql .= ",Relais1K2PVaus = ".$_POST["12PVWattaus"];
    $sql .= ",Relais1K2ACaus = ".$_POST["12ACWattaus"];
    $sql .= ",Relais1K2SMaus = ".$_POST["12SMWattaus"];
    $sql .= ",Relais1K2BMSaus = ".$_POST["12BMSWattaus"];
    $sql .= ",Relais1K2PVBedingungaus = \"".$_POST["12PVaus"]."\"";
    $sql .= ",Relais1K2ACBedingungaus = \"".$_POST["12ACaus"]."\"";
    $sql .= ",Relais1K2SMBedingungaus = \"".$_POST["12SMaus"]."\"";
    $sql .= " where Id = 1";
    $result = $db2->query( $sql );
    log_schreiben( $sql, "", 4 );
  }
  if (isset($_POST["13PVWattein"])) {
    $sql = "Update config set ";
    $sql .= "Relais1K3PVein = ".$_POST["13PVWattein"];
    $sql .= ",Relais1K3ACein = ".$_POST["13ACWattein"];
    $sql .= ",Relais1K3SMein = ".$_POST["13SMWattein"];
    $sql .= ",Relais1K3BMSein = ".$_POST["13BMSWattein"];
    $sql .= ",Relais1K3PVBedingungein = \"".$_POST["13PVein"]."\"";
    $sql .= ",Relais1K3ACBedingungein = \"".$_POST["13ACein"]."\"";
    $sql .= ",Relais1K3SMBedingungein = \"".$_POST["13SMein"]."\"";
    $sql .= ",Relais1K3PVaus = ".$_POST["13PVWattaus"];
    $sql .= ",Relais1K3ACaus = ".$_POST["13ACWattaus"];
    $sql .= ",Relais1K3SMaus = ".$_POST["13SMWattaus"];
    $sql .= ",Relais1K3BMSaus = ".$_POST["13BMSWattaus"];
    $sql .= ",Relais1K3PVBedingungaus = \"".$_POST["13PVaus"]."\"";
    $sql .= ",Relais1K3ACBedingungaus = \"".$_POST["13ACaus"]."\"";
    $sql .= ",Relais1K3SMBedingungaus = \"".$_POST["13SMaus"]."\"";
    $sql .= " where Id = 1";
    $result = $db2->query( $sql );
    log_schreiben( $sql, "", 4 );
  }
  if (isset($_POST["14PVWattein"])) {
    $sql = "Update config set ";
    $sql .= "Relais1K4PVein = ".$_POST["14PVWattein"];
    $sql .= ",Relais1K4ACein = ".$_POST["14ACWattein"];
    $sql .= ",Relais1K4SMein = ".$_POST["14SMWattein"];
    $sql .= ",Relais1K4BMSein = ".$_POST["14BMSWattein"];
    $sql .= ",Relais1K4PVBedingungein = \"".$_POST["14PVein"]."\"";
    $sql .= ",Relais1K4ACBedingungein = \"".$_POST["14ACein"]."\"";
    $sql .= ",Relais1K4SMBedingungein = \"".$_POST["14SMein"]."\"";
    $sql .= ",Relais1K4PVaus = ".$_POST["14PVWattaus"];
    $sql .= ",Relais1K4ACaus = ".$_POST["14ACWattaus"];
    $sql .= ",Relais1K4SMaus = ".$_POST["14SMWattaus"];
    $sql .= ",Relais1K4BMSaus = ".$_POST["14BMSWattaus"];
    $sql .= ",Relais1K4PVBedingungaus = \"".$_POST["14PVaus"]."\"";
    $sql .= ",Relais1K4ACBedingungaus = \"".$_POST["14ACaus"]."\"";
    $sql .= ",Relais1K4SMBedingungaus = \"".$_POST["14SMaus"]."\"";
    $sql .= " where Id = 1";
    $result = $db2->query( $sql );
    log_schreiben( $sql, "", 4 );
  }
  if (isset($_POST["21PVWattein"])) {
    $sql = "Update config set ";
    $sql .= "Relais2K1PVein = ".$_POST["21PVWattein"];
    $sql .= ",Relais2K1ACein = ".$_POST["21ACWattein"];
    $sql .= ",Relais2K1SMein = ".$_POST["21SMWattein"];
    $sql .= ",Relais2K1BMSein = ".$_POST["21BMSWattein"];
    $sql .= ",Relais2K1PVBedingungein = \"".$_POST["21PVein"]."\"";
    $sql .= ",Relais2K1ACBedingungein = \"".$_POST["21ACein"]."\"";
    $sql .= ",Relais2K1SMBedingungein = \"".$_POST["21SMein"]."\"";
    $sql .= ",Relais2K1PVaus = ".$_POST["21PVWattaus"];
    $sql .= ",Relais2K1ACaus = ".$_POST["21ACWattaus"];
    $sql .= ",Relais2K1SMaus = ".$_POST["21SMWattaus"];
    $sql .= ",Relais2K1BMSaus = ".$_POST["21BMSWattaus"];
    $sql .= ",Relais2K1PVBedingungaus = \"".$_POST["21PVaus"]."\"";
    $sql .= ",Relais2K1ACBedingungaus = \"".$_POST["21ACaus"]."\"";
    $sql .= ",Relais2K1SMBedingungaus = \"".$_POST["21SMaus"]."\"";
    $sql .= " where Id = 1";
    $result = $db2->query( $sql );
    log_schreiben( $sql, "", 4 );
  }
  if (isset($_POST["22PVWattein"])) {
    $sql = "Update config set ";
    $sql .= "Relais2K2PVein = ".$_POST["22PVWattein"];
    $sql .= ",Relais2K2ACein = ".$_POST["22ACWattein"];
    $sql .= ",Relais2K2SMein = ".$_POST["22SMWattein"];
    $sql .= ",Relais2K2BMSein = ".$_POST["22BMSWattein"];
    $sql .= ",Relais2K2PVBedingungein = \"".$_POST["22PVein"]."\"";
    $sql .= ",Relais2K2ACBedingungein = \"".$_POST["22ACein"]."\"";
    $sql .= ",Relais2K2SMBedingungein = \"".$_POST["22SMein"]."\"";
    $sql .= ",Relais2K2PVaus = ".$_POST["22PVWattaus"];
    $sql .= ",Relais2K2ACaus = ".$_POST["22ACWattaus"];
    $sql .= ",Relais2K2SMaus = ".$_POST["22SMWattaus"];
    $sql .= ",Relais2K2BMSaus = ".$_POST["22BMSWattaus"];
    $sql .= ",Relais2K2PVBedingungaus = \"".$_POST["22PVaus"]."\"";
    $sql .= ",Relais2K2ACBedingungaus = \"".$_POST["22ACaus"]."\"";
    $sql .= ",Relais2K2SMBedingungaus = \"".$_POST["22SMaus"]."\"";
    $sql .= " where Id = 1";
    $result = $db2->query( $sql );
    //$Anzahl = $db2->rowCount();
    //
    log_schreiben( $sql, "", 4 );
  }
}
if (isset($_POST["TestRelais1"]) or isset($_POST["TestRelais2"])) {
  $sql = "SELECT * FROM config";
  $result = $db2->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  if ($result == false) {
    $Fehlermeldung = "Es fehlen Eingaben!";
    log_schreiben( "Fehler! Es fehlen Eingaben.", "", 1 );
    goto Ausgang;
  }
  //  Alle Felder auslesen
  $var = $result[0];
  log_schreiben( print_r( $var, 1 ), "", 4 );

  /**************************************************************************
  //  Moscitto Client starten
  //
  **************************************************************************/
  $client = new Mosquitto\Client( );
  $client->onConnect( 'connect' );
  $client->onDisconnect( 'disconnect' );
  $client->onSubscribe( 'subscribe' );
  $client->onMessage( 'message' );
  $client->onLog( 'logger' );
  $client->onPublish( 'publish' );
  $client->connect( $var ["BrokerIP"], $var ["BrokerPort"], 5 );
  for ($i = 1; $i < 20; $i++) {
    // Warten bis der connect erfolgt ist.
    if (empty($Brokermeldung)) {
      $client->loop( 100 );
    }
    else {
      break;
    }
  }
  $client->loop( );
  if (isset($_POST["TestRelais1"])) {
    $client->subscribe( "+/".$_POST['Relais1Topic']."/#", 0 ); // Subscribe
    $client->subscribe( $_POST['Relais1Topic']."/#", 0 ); // Subscribe
  }
  else {
    $client->subscribe( "+/".$_POST['Relais2Topic']."/#", 0 ); // Subscribe
    $client->subscribe( $_POST['Relais2Topic']."/#", 0 ); // Subscribe
  }
  $client->loop( );
  for ($i = 1; $i < 20; $i++) {
    log_schreiben( print_r( $MQTTDaten, 1 ), "MQT", 4 );
    if (substr( $MQTTDaten["MQTTStatus"][1], - 7 ) == "PINGREQ") {
      log_schreiben( "Keine Antwort vom Broker (Relais). Abbruch!", "", 3 );
      break;
    }
    if (isset($MQTTDaten["MQTTMessageReturnText"]) and $MQTTDaten["MQTTMessageReturnText"] == "RX-OK") {

      /***********************************************************************
      //  MQTT Meldungen empfangen. Subscribing    Subscribing    Subscribing
      //  Hier werden die Daten vom Mosquitto Broker gelesen.
      ***********************************************************************/
      log_schreiben( print_r( $MQTTDaten, 1 ), "", 4 );
      log_schreiben( "Nachricht: ".$MQTTDaten["MQTTNachricht"], "", 3 );
      if (strtoupper( $MQTTDaten["MQTTNachricht"] ) == "ONLINE") {
        // Gerät ist online
        log_schreiben( "Topic: ".$MQTTDaten["MQTTTopic"], "   ", 3 );
        $TopicTeile = explode( "/", $MQTTDaten["MQTTTopic"] );
        if ($TopicTeile[0] == "tele") {
          $Prefix = 0;
          $TopicPosition = 1;
          $Payload = 2;
          if (isset($_POST["TestRelais1"])) {
            $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( "cmnd/".$_POST['Relais1Topic']."/status", 0, 0, false );
          }
          else {
            $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( "cmnd/".$_POST['Relais2Topic']."/status", 0, 0, false );
          }
        }
        elseif ($TopicTeile[1] == "tele") {
          $Prefix = 1;
          $TopicPosition = 0;
          $Payload = 2;
          if (isset($_POST["TestRelais1"])) {
            $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $_POST['Relais1Topic']."/cmnd/status", 0, 0, false );
          }
          else {
            $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $_POST['Relais2Topic']."/cmnd/status", 0, 0, false );
          }
        }
        $MQTTDaten = array();
      }
      if (isset($MQTTDaten["MQTTNachricht"])) {
        $values = json_decode( $MQTTDaten["MQTTNachricht"], true );
        if (is_array( $values )) {
          if (isset($values["Status"])) {
            log_schreiben( print_r( $values["Status"], 1 ), "", 1 );
            foreach ($values["Status"] as $k => $v) {
              $inputs[] = array("name" => $k, "value" => $v);
              if ($k == "DeviceName") {
                // Device Name
                $DeviceName = $v;
              }
              elseif ($k == "Power") {
                // Powerstatus
                $Power1 = ($v + 1);
              }
              elseif ($k == "Power1") {
                // Powerstatus
                $Power1 = ($v + 1);
              }
            }
            if ($Power1 == 2) {
              $Power1 = 0;
            }
          }
          if (isset($values["StatusSTS"])) {
            log_schreiben( print_r( $values["StatusSTS"], 1 ), "", 1 );
            foreach ($values["StatusSTS"] as $k => $v) {
              $inputs[] = array("name" => $k, "value" => $v);
              if ($k == "POWER1") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power1 = 0;
                else
                  $Power1 = 1;
              }
              if ($k == "POWER2") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power2 = 0;
                else
                  $Power2 = 1;
              }
              if ($k == "POWER3") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power3 = 0;
                else
                  $Power3 = 1;
              }
              if ($k == "POWER4") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power4 = 0;
                else
                  $Power4 = 1;
              }
            }
            log_schreiben( "TopicPosition: ".$TopicPosition, "", 1 );
            if ($TopicPosition == 0) {
              if (isset($_POST["TestRelais1"])) {
                $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $_POST['Relais1Topic']."/cmnd/power0", 2, 0, false );
              }
              else {
                $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $_POST['Relais2Topic']."/cmnd/power0", 2, 0, false );
              }
            }
            if ($TopicPosition == 1) {
              if (isset($_POST["TestRelais1"])) {
                $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( "cmnd/".$_POST['Relais1Topic']."/power0", 2, 0, false );
              }
              else {
                $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( "cmnd/".$_POST['Relais2Topic']."/power0", 2, 0, false );
              }
            }
            break;
          }
        }
      }
    }
    $client->loop( );
  }
  $var = array();
}
//log_schreiben($Power1." ".$Power2." ".$Power3." ".$Power4,"",2);

/*****************************************************************************
// Update der tabelle 'config' mit den Werten aus 'basiswerte'
*****************************************************************************/
if ($_POST["LR_ReglerNr"] > 0) {
  $sql = "SELECT * FROM basiswerte where Id = ".$_POST["LR_ReglerNr"];
  $result = $db1->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  //  Alle Felder auslesen
  $varBasiswerte = $result[0];
  log_schreiben( print_r( $varBasiswerte, 1 ), "", 2 );
  $sql = "Update config set ";
  $sql .= "LRMeasurement = \"".$varBasiswerte["LRMeasurement"]."\"";
  $sql .= ",LRFeldname = \"".$varBasiswerte["PVLeistung"]."\"";
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}
if ($_POST["WR_ReglerNr"] > 0) {
  $sql = "SELECT * FROM basiswerte where Id = ".$_POST["WR_ReglerNr"];
  $result = $db1->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  //  Alle Felder auslesen
  $varBasiswerte = $result[0];
  log_schreiben( print_r( $varBasiswerte, 1 ), "", 2 );
  $sql = "Update config set ";
  $sql .= "WRMeasurement = \"".$varBasiswerte["WRMeasurement"]."\"";
  $sql .= ",WRFeldname = \"".$varBasiswerte["ACLeistung"]."\"";
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}
if ($_POST["SM_ReglerNr"] > 0) {
  $sql = "SELECT * FROM basiswerte where Id = ".$_POST["SM_ReglerNr"];
  $result = $db1->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  //  Alle Felder auslesen
  $varBasiswerte = $result[0];
  log_schreiben( print_r( $varBasiswerte, 1 ), "", 2 );
  $sql = "Update config set ";
  $sql .= "SMMeasurement = \"".$varBasiswerte["MeterMeasurement"]."\"";
  $sql .= ",SMBezug = \"".$varBasiswerte["Bezug"]."\"";
  $sql .= ",SMEinspeisung = \"".$varBasiswerte["Einspeisung"]."\"";
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}
if ($_POST["BMS_ReglerNr"] > 0) {
  $sql = "SELECT * FROM basiswerte where Id = ".$_POST["BMS_ReglerNr"];
  $result = $db1->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  //  Alle Felder auslesen
  $varBasiswerte = $result[0];
  log_schreiben( print_r( $varBasiswerte, 1 ), "", 4 );
  $sql = "Update config set ";
  $sql .= "BMSMeasurement = \"".$varBasiswerte["BMSMeasurement"]."\"";
  $sql .= ",BMSFeldname = \"".$varBasiswerte["SOC"]."\"";
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}
if (isset($_POST["TestRelais1"])) {
  $sql = "Update config set ";
  $sql .= "Relais1Name = \"".$DeviceName."\"";
  $sql .= ",Relais1Kontakt1 = ".$Power1;
  $sql .= ",Relais1TopicFormat = ".$TopicPosition;
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}
if (isset($_POST["TestRelais2"])) {
  $sql = "Update config set ";
  $sql .= "Relais2Name = \"".$DeviceName."\"";
  $sql .= ",Relais2Kontakt1 = ".$Power1;
  $sql .= ",Relais2TopicFormat = ".$TopicPosition;
  $sql .= " where Id = 1";
  $result = $db2->query( $sql );
}

/*****************************************************************************/
$sql = "SELECT * FROM config";
$result = $db2->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
//  Alle Felder auslesen
$var = $result[0];
// $var            ($db2)
foreach ($var as $key => $wert) {
  if ($wert == "null") {
    $var [$key] = "";
  }
}

/*   Die Variablen auf 0 setzen, falls "NULL" enthalten ist
//   Da sie zum EIN / AUS schalten der Funktion benutzt werden.
****************************************************************************/
if ($var ["Relais1K1ausMinuten"] == "") {
  $var ["Relais1K1ausMinuten"] = 0;
}
if ($var ["Relais1K1einMinuten"] == "") {
  $var ["Relais1K1einMinuten"] = 0;
}
if ($var ["Relais1K2ausMinuten"] == "") {
  $var ["Relais1K2ausMinuten"] = 0;
}
if ($var ["Relais1K2einMinuten"] == "") {
  $var ["Relais1K2einMinuten"] = 0;
}
if ($var ["Relais1K3ausMinuten"] == "") {
  $var ["Relais1K3ausMinuten"] = 0;
}
if ($var ["Relais1K3einMinuten"] == "") {
  $var ["Relais1K3einMinuten"] = 0;
}
if ($var ["Relais1K4ausMinuten"] == "") {
  $var ["Relais1K4ausMinuten"] = 0;
}
if ($var ["Relais1K4einMinuten"] == "") {
  $var ["Relais1K4einMinuten"] = 0;
}

/***********************************************************
//  Werden noch nicht benutzt

if ($var["Relais2K1ausMinuten"] == "")  {
$var["Relais2K1ausMinuten"] = 0;
}
if ($var["Relais2K1einMinuten"] == "")  {
$var["Relais2K1einMinuten"] = 0;
}
if ($var["Relais2K2ausMinuten"] == "")  {
$var["Relais2K2ausMinuten"] = 0;
}
if ($var["Relais2K2einMinuten"] == "")  {
$var["Relais2K2einMinuten"] = 0;
}
if ($var["Relais2K3ausMinuten"] == "")  {
$var["Relais2K3ausMinuten"] = 0;
}
if ($var["Relais2K3einMinuten"] == "")  {
$var["Relais2K3einMinuten"] = 0;
}
if ($var["Relais2K4ausMinuten"] == "")  {
$var["Relais2K4ausMinuten"] = 0;
}
if ($var["Relais2K4einMinuten"] == "")  {
$var["Relais2K4einMinuten"] = 0;
}
***********************************************************/
// log_schreiben(print_r($var,1),"",3);
Ausgang:$db1 = null;
$db2 = null;
log_schreiben( "---------------------------------------------------------", "ENDE", 1 );
include ("automation.html");
exit;
function db_connect( $Database ) {
  return new PDO( 'sqlite:'.$Database );
}
function influxDB_lesen( $Datenbankname, $Measurement ) {
  // Alle aktuellen Daten eines Measurement lesen.
  //
  $ch = curl_init( 'http://localhost/query?db='.$Datenbankname.'&precision=s&q='.urlencode( 'select * from '.$Measurement.' order by time desc limit 1' ));
  $rc = datenbank( $ch );
  if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
    if (isset($rc["JSON_Ausgabe"]["results"][0]["error"])) {
      log_schreiben( $rc["JSON_Ausgabe"]["results"][0]["error"], "", 1 );
      return false;
    }
    log_schreiben( "Keine Daten vorhanden.", "", 1 );
    return false;
  }
  else {
    for ($i = 1; $i < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $i++) {
      $influxDB[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$i]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$i];
    }
    log_schreiben( "Datenbank: '".$Datenbankname."' ".print_r( $influxDB, 1 ), "", 4 );
  }
  return $influxDB;
}
function datenbank( $ch, $query = "" ) {
  $Ergebnis = array();
  $Ergebnis["Ausgabe"] = false;
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 15 ); //timeout in second s
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 12 );
  curl_setopt( $ch, CURLOPT_PORT, 8086 );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $Ergebnis["result"] = curl_exec( $ch );
  $Ergebnis["rc_info"] = curl_getinfo( $ch );
  $Ergebnis["JSON_Ausgabe"] = json_decode( $Ergebnis["result"], true, 10 );
  $Ergebnis["errorno"] = curl_errno( $ch );
  if ($Ergebnis["rc_info"]["http_code"] == 200 or $Ergebnis["rc_info"]["http_code"] == 204) {
    $Ergebnis["Ausgabe"] = true;
  }
  curl_close( $ch );
  unset($ch);
  return $Ergebnis;
}

/**************************************************************************
//  Log Eintrag in die Logdatei schreiben
//  $LogMeldung = Die Meldung ISO Format
//  $Loglevel=2   Loglevel 1-4   4 = Trace
**************************************************************************/
function log_schreiben( $LogMeldung, $Titel = "", $Loglevel = 3, $UTF8 = 0 ) {
  global $Tracelevel;
  $LogDateiName = "../log/WEB-automation.log";
  if (strlen( $Titel ) < 4) {
    switch ($Loglevel) {

      case 1:
        $Titel = "ERRO";
        break;

      case 2:
        $Titel = "WARN";
        break;

      case 3:
        $Titel = "INFO";
        break;

      default:
        $Titel = "    ";
        break;
    }
  }
  if ($Loglevel <= $Tracelevel) {
    if ($UTF8) {
      $LogMeldung = utf8_encode( $LogMeldung );
    }
    if ($handle = fopen( $LogDateiName, 'a' )) {
      //  Schreibe in die geöffnete Datei.
      //  Bei einem Fehler bis zu 3 mal versuchen.
      for ($i = 1; $i < 4; $i++) {
        $rc = fwrite( $handle, date( "d.m. H:i:s" )." ".substr( $Titel, 0, 4 )." ".$LogMeldung."\n" );
        if ($rc) {
          break;
        }
        sleep( 1 );
      }
      fclose( $handle );
    }
  }
  return true;
}
function connect( $r, $message ) {
  global $Brokermeldung;
  log_schreiben( "Broker: ".$message, "", 3 );
  $Brokermeldung = $message;
}
function publish( ) {
  log_schreiben( "Mesage published.", "", 3 );
}
function disconnect( ) {
  // log_schreiben("Broker disconnect erfolgreich.","",3);
}
function subscribe( ) {
  log_schreiben( "Subscribed to a topic.", "", 3 );
  // echo "subscribe\n";
}
function logger( ) {
  global $MQTTDaten;
  log_schreiben( print_r( func_get_args( ), 1 ), "", 4 );
  $p = func_get_args( );
  $MQTTDaten["MQTTStatus"] = $p;
}
function message( $message ) {
  global $MQTTDaten;
  $MQTTDaten["MQTTRetain"] = 0;
  $MQTTDaten["MQTTMessageReturnText"] = "RX-OK";
  $MQTTDaten["MQTTNachricht"] = $message->payload;
  $MQTTDaten["MQTTTopic"] = $message->topic;
  $MQTTDaten["MQTTQos"] = $message->qos;
  $MQTTDaten["MQTTMid"] = $message->mid;
  $MQTTDaten["MQTTRetain"] = $message->retain;
}
?>