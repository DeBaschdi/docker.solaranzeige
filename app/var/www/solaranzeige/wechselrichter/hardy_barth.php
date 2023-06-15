#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2022]  [Ulrich Kunz]
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
//  Es dient dem Auslesen der Hardy Barth eCB1 Wallbox über das LAN.
//  Das Salia Modell ist noch nicht fertig integriert!
//  ----------------          ------------       
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen( );
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Header ="";
$Device = "WB"; // WB = Wallbox
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  hardy_barth.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
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
$funktionen->log_schreiben( "Hardware Version: ".$Version, "o  ", 8 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProLadung.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenProLadung"] = file_get_contents( $StatusFile );
  $funktionen->log_schreiben( "WattstundenProLadung: ".round( $aktuelleDaten["WattstundenProLadung"], 2 ), "   ", 8 );
  if (empty($aktuelleDaten["WattstundenProLadung"])) {
    $aktuelleDaten["WattstundenProLadung"] = 0;
  }
}
else {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei WhProLadung.txt nicht anlegen.", "XX ", 5 );
  }
}
if (empty($WR_Adresse)) {
  $WBID = "1";
}
else {
  $WBID = (intval( $WR_Adresse ));
}
$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i > 6) {
      //  Es werden nur maximal 6 Befehle pro Datei verarbeitet!
      break;
    }

    /*********************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  QPI ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    *********************************************************************************/
    if (file_exists( $Pfad."/befehle.ini.php" )) {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $Pfad.'/befehle.ini.php', true );
      $Regler60 = $INI_File["Regler60"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler60, 1 ), "|- ", 8 );
      foreach ($Regler60 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          $funktionen->log_schreiben( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 10 );
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        $funktionen->log_schreiben( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
        $funktionen->log_schreiben( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
        break;
      }
    }
    else {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
      break;
    }
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    // Auf "manual" Mode umschalten! Damit Befehle entgegemngenommen werden.
    $http_daten = array("URL" => "http://".$WR_IP."/api/v1/pvmode", "Port" => $WR_Port, "Header" => array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'), "Data" => "pvmode=manual");
    $Daten = $funktionen->http_read( $http_daten );
    $funktionen->log_schreiben( "Wallbox auf PVMode = manual  umschalten", "   ", 7 );
    // Hier wird der Befehl gesendet...
    // -----------------------------------------------------
    // $Teile[0] , $Teile[1]
    if ($Teile[0] == "start") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/chargecontrols/".$WBID."/start", "Port" => $WR_Port, "Header" => array('Content-Type: application/json', 'Accept: application/json', 'Content-length: 0'), "Data" => "");
      $Daten = $funktionen->http_read( $http_daten );
      if (isset($Daten["errors"]["message"])) {
        $funktionen->log_schreiben( "Fehler!: ".$Daten["errors"]["message"], "   ", 1 );
      }
      $funktionen->log_schreiben( "Wallbox: Ladung gestartet.", "   ", 7 );
    }
    elseif ($Teile[0] == "stop") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/chargecontrols/".$WBID."/stop", "Port" => $WR_Port, "Header" => array('Content-Type: application/json', 'Accept: application/json', 'Content-length: 0'), "Data" => "");
      $Daten = $funktionen->http_read( $http_daten );
      if (isset($Daten["errors"]["message"])) {
        $funktionen->log_schreiben( "Fehler!: ".$Daten["errors"]["message"], "   ", 1 );
      }
      $funktionen->log_schreiben( "Wallbox: Ladung unterbrochen.", "   ", 7 );
    }
    elseif ($Teile[0] == "ampere") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/pvmode/manual/ampere", "Port" => $WR_Port, "Header" => array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'), "Data" => "manualmodeamp=".$Teile[1]);
      $Daten = $funktionen->http_read( $http_daten );
      $funktionen->log_schreiben( "Wallbox: Stromstärke eingestellt auf ".$Teile[1]." Ampere", "   ", 7 );
    }
    // -----------------------------------------------------
  }
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }
  sleep( 3 );
}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}
$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ****************************************************************************/
  $URL = "api/v1/meters/".$WBID."/";
  $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($i >= 2) {
      $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
      break;
    }
    $i++;
    continue;
  }
  if (!isset($Daten["meter"]["name"])) {
    // Es ist ein Salia Modell
    $aktuelleDaten["Name"] = $Daten["device"]["modelname"];
    $aktuelleDaten["wbsaldoactive"] = 0;
    $aktuelleDaten["Wh_Leistung_aktuell"] = 
    $aktuelleDaten["Wh_Gesamtleistung"] = $Daten["secc"]["port0"]["metering"]["energy"]["active_total"]["actual"];
    $aktuelleDaten["Leistungsfaktor"] = 0;
    $aktuelleDaten["Leistung_R"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l1"]["actual"];
    $aktuelleDaten["Strom_R"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l1"]["actual"] / 100);
    $aktuelleDaten["Spannung_R"] = 230;
    $aktuelleDaten["Leistungsfaktor_R"] = 0;
    $aktuelleDaten["Leistung_S"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l2"]["actual"];
    $aktuelleDaten["Strom_S"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l2"]["actual"] / 100);
    $aktuelleDaten["Spannung_S"] = 230;
    $aktuelleDaten["Leistungsfaktor_S"] = 0;
    $aktuelleDaten["Leistung_T"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l3"]["actual"];
    $aktuelleDaten["Strom_T"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l3"]["actual"] / 100);
    $aktuelleDaten["Spannung_T"] = 230;
    $aktuelleDaten["Leistungsfaktor_T"] = 0;
    $aktuelleDaten["Seriennummer"] = $Daten["device"]["serial"];
    $aktuelleDaten["Protokoll-Version"] = $Daten["device"]["software_version"];
    $aktuelleDaten["Mode"] = $Daten["secc"]["port0"]["charging"];
    $aktuelleDaten["LadungAmpere"] = ($aktuelleDaten["Strom_R"] + $aktuelleDaten["Strom_S"] + $aktuelleDaten["Strom_T"]);
    $aktuelleDaten["Status"] = $Daten["secc"]["port0"]["ci"]["charge"]["cp"]["status"];
    $aktuelleDaten["AmpereVorgabe"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["grid_current_limit"]["actual"];
    $aktuelleDaten["MaxAmpere"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["offered_current_limit"];
    $aktuelleDaten["Anz_Phasen"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["phase_count"];
    $aktuelleDaten["Hersteller"] = $Daten["device"]["modelname"];
    $aktuelleDaten["ModeID"] = 0;
    $aktuelleDaten["StateID"] = $Daten["secc"]["port0"]["cp"]["pwm_state"]["actual"];
    $aktuelleDaten["Wh_Leistung_aktuell"] = $Daten["secc"]["port0"]["metering"]["power"]["active_total"]["actual"];;

    if ($Daten["secc"]["port0"]["ci"]["charge"]["plug"]["status"] == "locked") {
      $aktuelleDaten["Connected"] = 1;
    }
    else {
      $aktuelleDaten["Connected"] = 0;
    }
    $aktuelleDaten["PVMode"] = $Daten["secc"]["port0"]["salia"]["chargemode"];

    $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, "api/", $Header ), 1 ), "o=>", 9 );

  }
  else {
    $aktuelleDaten["Name"] = $Daten["meter"]["name"];
    $aktuelleDaten["wbsaldoactive"] = $Daten["meter"]["data"]["lgwb"];
    $aktuelleDaten["Wh_Leistung_aktuell"] = $Daten["meter"]["data"]["1-0:1.4.0"];
    $aktuelleDaten["Wh_Gesamtleistung"] = $Daten["meter"]["data"]["1-0:1.8.0"] * 1000;
    $aktuelleDaten["Leistungsfaktor"] = $Daten["meter"]["data"]["1-0:13.4.0"];
    $aktuelleDaten["Leistung_R"] = $Daten["meter"]["data"]["1-0:21.4.0"];
    $aktuelleDaten["Strom_R"] = $Daten["meter"]["data"]["1-0:31.4.0"];
    $aktuelleDaten["Spannung_R"] = $Daten["meter"]["data"]["1-0:32.4.0"];
    $aktuelleDaten["Leistungsfaktor_R"] = $Daten["meter"]["data"]["1-0:33.4.0"];
    $aktuelleDaten["Leistung_S"] = $Daten["meter"]["data"]["1-0:41.4.0"];
    $aktuelleDaten["Strom_S"] = $Daten["meter"]["data"]["1-0:51.4.0"];
    $aktuelleDaten["Spannung_S"] = $Daten["meter"]["data"]["1-0:52.4.0"];
    $aktuelleDaten["Leistungsfaktor_S"] = $Daten["meter"]["data"]["1-0:53.4.0"];
    $aktuelleDaten["Leistung_T"] = $Daten["meter"]["data"]["1-0:61.4.0"];
    $aktuelleDaten["Strom_T"] = $Daten["meter"]["data"]["1-0:71.4.0"];
    $aktuelleDaten["Spannung_T"] = $Daten["meter"]["data"]["1-0:72.4.0"];
    $aktuelleDaten["Leistungsfaktor_T"] = $Daten["meter"]["data"]["1-0:73.4.0"];
    $aktuelleDaten["Seriennummer"] = $Daten["meter"]["serial"];
    $aktuelleDaten["Protokoll-Version"] = $Daten["protocol-version"];
    $URL = "api/v1/chargecontrols/".$WBID;
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    //  Status:
    //  A = Ready
    //  B = Ladung aktiv
    //  C = Fehler
    //  D = Fehler
    //  Kein Strom
    $aktuelleDaten["Mode"] = $Daten["chargecontrol"]["mode"];
    $aktuelleDaten["LadungAmpere"] = $Daten["chargecontrol"]["currentpwmamp"];
    $aktuelleDaten["Status"] = $Daten["chargecontrol"]["state"];
    $aktuelleDaten["AmpereVorgabe"] = $Daten["chargecontrol"]["manualmodeamp"];
    $aktuelleDaten["MaxAmpere"] = $Daten["chargecontrol"]["supplylinemaxamp"];
    $aktuelleDaten["Connected"] = $Daten["chargecontrol"]["connected"];
    $aktuelleDaten["ModeID"] = $Daten["chargecontrol"]["modeid"];
    $aktuelleDaten["StateID"] = $Daten["chargecontrol"]["stateid"];
    $aktuelleDaten["Hersteller"] = $Daten["chargecontrol"]["vendor"];
    if ($Daten["chargecontrol"]["connected"] <> 1) {
      $aktuelleDaten["Connected"] = 0;
    }
    else {
      $aktuelleDaten["Connected"] = 1;
    }
    $URL = "api/v1/rfidtags";
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    $URL = "api/v1/pvmode";
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    $aktuelleDaten["PVMode"] = $Daten["pvmode"]; //  eco, quick, manual
  }

  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = $aktuelleDaten["Protokoll-Version"];
  $aktuelleDaten["Produkt"] = "Hardy Barth";
  $aktuelleDaten["Wh_Ladevorgang"] = round( $aktuelleDaten["WattstundenProLadung"] );
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/hardy_barth_math.php" )) {
    include 'hardy_barth_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require ($Pfad."/mqtt_senden.php");
  }
  if ($aktuelleDaten["Connected"] == 0 and $aktuelleDaten["WattstundenProLadung"] > 0) {
    $aktuelleDaten["WattstundenProLadung"] = 0; // Zähler pro Ladung zurücksetzen
    $rc = file_put_contents( $StatusFile, "0" );
    $funktionen->log_schreiben( "WattstundenProLadung gelöscht.", "    ", 5 );
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
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // Dummy

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
      $rc = $funktionen->influx_remote_test( );
      if ($rc) {
        $rc = $funktionen->influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if (is_file( $Pfad."/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (1 == 1) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben( "Nachrichten versenden...", "   ", 8 );
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  $funktionen->log_schreiben( "Keine gültigen Daten empfangen.", "!! ", 6 );
}
fclose( $COM );

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["Connected"] == 1) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Ladung = Wh
  ***************************************************************************/
  $whProLadung = file_get_contents( $StatusFile );
  $whProLadung = ($whProLadung + ($aktuelleDaten["Wh_Leistung_aktuell"] / 60));
  $rc = file_put_contents( $StatusFile, $whProLadung );
  $funktionen->log_schreiben( "WattstundenProLadung: ".round( $whProLadung ), "   ", 5 );
}
Ausgang:$funktionen->log_schreiben( "-------------   Stop   hardy_arth.php   ---------------------- ", "|--", 6 );
return;
?>
