#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Ahoy DTU über das LAN.
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
$Device = "DTU"; // DTU
$Start = time( ); // Timestamp festhalten
$aktuelleDaten = array();
$funktionen->log_schreiben( "----------------   Start  ahoy.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$Version = "";
//$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
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
$funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 1 );
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
$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Ahoy-DTU ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}

/************************************************************************************
//  Sollen Befehle an den Wechselrichter gesendet werden?
//
************************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i > 10) {
      //  Es werden nur maximal 10 Befehle pro Datei verarbeitet!
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
      $Regler88 = $INI_File["Regler88"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler88, 1 ), "|- ", 9 );
      $Subst = $Befehle[$i];
      foreach ($Regler88 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
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
    $Wert = false;
    $Antwort = "";
    $http_daten = array();

    /************************************************************************
    //  Ab hier wird der Befehl gesendet.
    //  $Befehle[$i] = aktueller Befehl
    ************************************************************************/
    $Teile = explode( "_", $Befehle[$i] );
    $InverterNr = ((int) substr( $Befehle[$i], 1, 2 ) - 1);
    $SetWatt = $Teile[1];
    if (substr( strtoupper( $Befehle[$i] ), 3, 1 ) == "W" and substr( strtoupper( $Befehle[$i] ), 4, 1 ) == "P") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/ctrl", "Request" => "POST", "Port" => $WR_Port, "Header" => array('Content-Type: application/json'), "Data" => '{"id":'.$InverterNr.' ,"cmd": "limit_persistent_absolute", "val":'.$SetWatt.'}');
    }
    elseif (substr( strtoupper( $Befehle[$i] ), 3, 1 ) == "P" and substr( strtoupper( $Befehle[$i] ), 4, 1 ) == "P") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/ctrl", "Request" => "POST", "Port" => $WR_Port, "Header" => array('Content-Type: application/json'), "Data" => '{"id":'.$InverterNr.' ,"cmd": "limit_persistent_relative", "val":'.$SetWatt.'}');
    }
    elseif (substr( strtoupper( $Befehle[$i] ), 3, 1 ) == "W" and substr( strtoupper( $Befehle[$i] ), 4, 1 ) == "S") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/ctrl", "Request" => "POST", "Port" => $WR_Port, "Header" => array('Content-Type: application/json'), "Data" => '{"id":'.$InverterNr.' ,"cmd": "limit_nonpersistent_absolute", "val":'.$SetWatt.'}');
    }
    elseif (substr( strtoupper( $Befehle[$i] ), 3, 1 ) == "P" and substr( strtoupper( $Befehle[$i] ), 4, 1 ) == "S") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/ctrl", "Request" => "POST", "Port" => $WR_Port, "Header" => array('Content-Type: application/json'), "Data" => '{"id":'.$InverterNr.' ,"cmd": "limit_nonpersistent_relative", "val":'.$SetWatt.'}');
    }
    else {
      $funktionen->log_schreiben( 'Fehler im Befehl: '.$Befehle[$i], "   ", 1 );
    }
    $Daten = $funktionen->http_read( $http_daten );
    if ($Daten["success"] === true) {
      $funktionen->log_schreiben( "Befehl ".$Befehle[$i]." erfolgreich ausgeführt", "   ", 1 );
    }
    else {
      $funktionen->log_schreiben( "Befehl ".$Befehle[$i]." nicht ausgeführt", "   ", 1 );
    }
  }
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 8 );
  }
}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}
$k = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die AhoyDTU ausgelesen.
  //
  ****************************************************************************/
  $URL = "api/system";
  $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($k >= 2) {
      $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 9 );
      break;
    }
    $k++;
    continue;
  }
  if (isset($Daten["version"])) {
    // Es ist dia alte Ahoy Firmware Version 0.5.x
    $funktionen->log_schreiben( "Alte Version 0.5.x.", "   ", 3 );
    $aktuelleDaten["Info"]["DeviceName.Text"] = $Daten["device_name"];
    $aktuelleDaten["Info"]["Firmware.Text"] = $Daten["version"];
    $URL = "api/inverter/list";
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($k >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 9 );
        break;
      }
      $k++;
      continue;
    }
    $Anz_Inverter = count( $Daten["inverter"] );
    for ($i = 0; $i < $Anz_Inverter; $i++) {
      $Anz_Channels = $Daten["inverter"][$i]["channels"];
      for ($j = 0; $j < $Anz_Channels; $j++) {
        if (is_numeric( $Daten["inverter"][$i]["ch_name"][$j] )) {
          $Measurement = "Port".$Daten["inverter"][$i]["ch_name"][$j];
        }
        else {
          $Measurement = str_replace( " ", "", $Daten["inverter"][$i]["ch_name"][$j] );
        }
        $aktuelleDaten[$Measurement]["Max_Power"] = $Daten["inverter"][$i]["ch_max_power"][$j];
        $aktuelleDaten[$Measurement]["Seriennummer"] = $Daten["inverter"][$i]["serial"];
      }
    }
    $URL = "api/live";
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($k >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 9 );
        break;
      }
      $k++;
      continue;
    }
    $Anz_Inverter = count( $Daten["inverter"] );
    $aktuelleDaten["DTU"]["DC_Leistung"] = 0;
    $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] = 0;
    $aktuelleDaten["DTU"]["Energie_Inverter_Total"] = 0;
    $aktuelleDaten["DTU"]["AC_Leistung"] = 0;
    $aktuelleDaten["Info"]["Firmware.Text"] = $Daten["generic"]["version"];
    for ($i = 0; $i < $Anz_Inverter; $i++) {
      if (is_numeric( $Daten["inverter"][$i]["name"] )) {
        $Measurement = "Inverter".$Daten["inverter"][$i]["name"];
      }
      else {
        $Measurement = str_replace( " ", "", $Daten["inverter"][$i]["name"] );
      }
      $aktuelleDaten[$Measurement]["Aktiv"] = (int) $Daten["inverter"][$i]["enabled"];
      $aktuelleDaten[$Measurement]["Anz_Channel"] = $Daten["inverter"][$i]["channels"];
      $aktuelleDaten[$Measurement]["LimitPower"] = $Daten["inverter"][$i]["power_limit_read"];
      $aktuelleDaten[$Measurement]["Name.Text"] = $Daten["inverter"][$i]["name"];
      $aktuelleDaten[$Measurement]["Status.Text"] = $Daten["inverter"][$i]["last_alarm"];
      $aktuelleDaten[$Measurement]["AC_Spannung"] = $Daten["inverter"][$i]["ch"][0][0];
      $aktuelleDaten[$Measurement]["AC_Strom"] = $Daten["inverter"][$i]["ch"][0][1];
      $aktuelleDaten[$Measurement]["AC_Leistung"] = $Daten["inverter"][$i]["ch"][0][2];
      $aktuelleDaten[$Measurement]["AC_Scheinleistung"] = $Daten["inverter"][$i]["ch"][0][10];
      $aktuelleDaten[$Measurement]["Frequenz"] = $Daten["inverter"][$i]["ch"][0][3];
      $aktuelleDaten[$Measurement]["PF"] = $Daten["inverter"][$i]["ch"][0][4];
      $aktuelleDaten[$Measurement]["Temperatur"] = $Daten["inverter"][$i]["ch"][0][5];
      $aktuelleDaten[$Measurement]["Energie_Inverter_Heute"] = $Daten["inverter"][$i]["ch"][0][7];
      $aktuelleDaten[$Measurement]["Energie_Inverter_Total"] = ($Daten["inverter"][$i]["ch"][0][6] * 1000);
      $aktuelleDaten[$Measurement]["DC_Leistung"] = $Daten["inverter"][$i]["ch"][0][8];
      $aktuelleDaten[$Measurement]["Effizienz"] = $Daten["inverter"][$i]["ch"][0][9];
      $aktuelleDaten["DTU"]["DC_Leistung"] = $aktuelleDaten["DTU"]["DC_Leistung"] + (int) $Daten["inverter"][$i]["ch"][0][8];
      $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] = $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] + (int) $Daten["inverter"][$i]["ch"][0][7];
      $aktuelleDaten["DTU"]["Energie_Inverter_Total"] = $aktuelleDaten["DTU"]["Energie_Inverter_Total"] + ($Daten["inverter"][$i]["ch"][0][6] * 1000);
      $aktuelleDaten["DTU"]["AC_Leistung"] = $aktuelleDaten["DTU"]["AC_Leistung"] + (int) $Daten["inverter"][$i]["ch"][0][2];
      if ($i == 0) {
        $aktuelleDaten["DTU"]["Temperatur"] = $Daten["inverter"][$i]["ch"][0][5];
      }
      $Anz_Channels = $Daten["inverter"][$i]["channels"];
      for ($j = 1; $j <= $Anz_Channels; $j++) {
        if (is_numeric( $Daten["inverter"][$i]["ch_names"][$j] )) {
          $Measurement = "Inv".($i+1)."Port".$Daten["inverter"][$i]["ch_names"][$j];
        }
        else {
          $Measurement = str_replace( " ", "", $Daten["inverter"][$i]["ch_names"][$j] );
        }
        if (strlen( $Measurement ) == 0) {
          $Measurement = "Port".$j;
        }
        $aktuelleDaten[$Measurement]["Portnummer"] = (int) ($i.$j);
        $aktuelleDaten[$Measurement]["PV_Spannung"] = $Daten["inverter"][$i]["ch"][$j][0];
        $aktuelleDaten[$Measurement]["PV_Strom"] = $Daten["inverter"][$i]["ch"][$j][1];
        $aktuelleDaten[$Measurement]["PV_Leistung"] = $Daten["inverter"][$i]["ch"][$j][2];
        $aktuelleDaten[$Measurement]["PV_Energie_Heute"] = $Daten["inverter"][$i]["ch"][$j][3];
        $aktuelleDaten[$Measurement]["PV_Energie_Total"] = ($Daten["inverter"][$i]["ch"][$j][4] * 1000);
        $aktuelleDaten[$Measurement]["Irradiation"] = $Daten["inverter"][$i]["ch"][$j][5];
        if ($i == 0) {
          $aktuelleDaten["DTU"]["PV".$j."_Leistung"] = $Daten["inverter"][0]["ch"][$j][2];
        }
      }
    }
  }
  else {
    // Es ist die neue Ahoy Firmware Version 0.6.x
    $funktionen->log_schreiben( "Neue Version 0.6.x.", "   ", 3 );
    $aktuelleDaten["Info"]["DeviceName.Text"] = $Daten["device_name"];
    $aktuelleDaten["Info"]["Firmware.Text"] = $Daten["sdk"];
    $URL = "api/inverter/list";
    $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($k >= 2) {
        $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 7 );
        break;
      }
      $k++;
      continue;
    }
    $funktionen->log_schreiben( var_export( $Daten,1), "o=>", 10 );
    $Anz_Inverter = count( $Daten["inverter"] );
    for ($i = 0; $i < $Anz_Inverter; $i++) {
      $Anz_Channels = $Daten["inverter"][$i]["channels"];
      $Measurement = "Inverter".($i+1);
      $aktuelleDaten[$Measurement]["Seriennummer"] = $Daten["inverter"][$i]["serial"];
      $URL = "api/inverter/id/".$i;
      $Daten[$Inv] = $funktionen->read( $WR_IP, $WR_Port, $URL );
      if ($Daten[$Inv] === false) {
        $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
        if ($k >= 2) {
          $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 7 );
          break;
        }
        $i++;
        continue;
      }
      $funktionen->log_schreiben( "==>".var_export( $funktionen->read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 10 );
      $aktuelleDaten["DTU"]["DC_Leistung"] = 0;
      $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] = 0;
      $aktuelleDaten["DTU"]["Energie_Inverter_Total"] = 0;
      $aktuelleDaten["DTU"]["AC_Leistung"] = 0;
      $aktuelleDaten[$Measurement]["Aktiv"] = (int) $Daten[$Inv]["enabled"];
      $aktuelleDaten[$Measurement]["LimitPower"] = $Daten[$Inv]["power_limit_read"];
      $aktuelleDaten[$Measurement]["Name.Text"] = $Daten[$Inv]["name"];
      $aktuelleDaten[$Measurement]["AC_Spannung"] = $Daten[$Inv]["ch"][0][0];
      $aktuelleDaten[$Measurement]["AC_Strom"] = $Daten[$Inv]["ch"][0][1];
      $aktuelleDaten[$Measurement]["AC_Leistung"] = $Daten[$Inv]["ch"][0][2];
      $aktuelleDaten[$Measurement]["AC_Scheinleistung"] = $Daten[$Inv]["ch"][0][10];
      $aktuelleDaten[$Measurement]["Frequenz"] = $Daten[$Inv]["ch"][0][3];
      $aktuelleDaten[$Measurement]["PF"] = $Daten[$Inv]["ch"][0][4];
      $aktuelleDaten[$Measurement]["Temperatur"] = $Daten[$Inv]["ch"][0][5];
      $aktuelleDaten[$Measurement]["Energie_Inverter_Total"] = ($Daten[$Inv]["ch"][0][6] * 1000);
      $aktuelleDaten[$Measurement]["Energie_Inverter_Heute"] = $Daten[$Inv]["ch"][0][7];
      $aktuelleDaten[$Measurement]["DC_Leistung"] = $Daten[$Inv]["ch"][0][8];
      $aktuelleDaten[$Measurement]["Effizienz"] = $Daten[$Inv]["ch"][0][9];
      $aktuelleDaten["DTU"]["DC_Leistung"] = $aktuelleDaten["DTU"]["DC_Leistung"] + (int) $Daten[$Inv]["ch"][0][8];
      $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] = $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] + (int) $Daten[$Inv]["ch"][0][7];
      $aktuelleDaten["DTU"]["Energie_Inverter_Total"] = $aktuelleDaten["DTU"]["Energie_Inverter_Total"] + ($Daten[$Inv]["ch"][0][6] * 1000);
      $aktuelleDaten["DTU"]["AC_Leistung"] = $aktuelleDaten["DTU"]["AC_Leistung"] + (int) $Daten[$Inv]["ch"][0][2];
      if ($i == 0) {
        $aktuelleDaten["DTU"]["Temperatur"] = $Daten[$Inv]["ch"][0][5];
      }
      for ($j = 1; $j <= $Anz_Channels; $j++) {
        $aktuelleDaten[$Measurement]["PV".$j."_Spannung"] = $Daten[$Inv]["ch"][$j][0];
        $aktuelleDaten[$Measurement]["PV".$j."_Strom"] = $Daten[$Inv]["ch"][$j][1];
        $aktuelleDaten[$Measurement]["PV".$j."_Leistung"] = $Daten[$Inv]["ch"][$j][2];
        $aktuelleDaten[$Measurement]["PV".$j."_Energie_Heute"] = $Daten[$Inv]["ch"][$j][3];
        $aktuelleDaten[$Measurement]["PV".$j."_Energie_Total"] = ($Daten[$Inv]["ch"][$j][4] * 1000);
        $aktuelleDaten[$Measurement]["Irradiation".$j] = $Daten[$Inv]["ch"][$j][5];
        $aktuelleDaten["DTU"]["PV".$i.$j."_Leistung"] = $Daten[$Inv]["ch"][$j][2];
      }
      $funktionen->log_schreiben( "Seriennummer: ".$aktuelleDaten[$Measurement]["Seriennummer"], "   ", 1 );
    }
  }
  $funktionen->log_schreiben( print_r( $Daten, 1 ), "   ", 10 );

  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["Info"]["Modell.Text"] = "Ahoy DTU";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 10 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/ahoy_math.php" )) {
    include 'ahoy_math.php'; // Falls etwas neu berechnet werden muss.
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
    $funktionen->log_schreiben( "Schleife: ".($k)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $k + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $k + 1)));
  }
  if ($Wiederholungen <= $k or $k >= 6) {
    $funktionen->log_schreiben( "Schleife ".$k." Ausgang...", "   ", 5 );
    break;
  }
  $k++;
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

/******/
Ausgang:

/******/
$funktionen->log_schreiben( "----------------   Stop   ahoy.php   ---------------------- ", "|--", 6 );
return;
?>
