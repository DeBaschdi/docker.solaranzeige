#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Wallbe Wallbox über das LAN
//
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
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-----------------   Start  webasto_wb.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( $WR_Adresse, 2, "0", STR_PAD_LEFT );
}
else {
  $WR_ID = str_pad( substr( $WR_Adresse, - 2 ), 2, "0", STR_PAD_LEFT );
}
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
$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  start = 1    amp = 6
//  Per HTTP  start_1      amp_6
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {

  /*************/
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 1 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i >= 6) {
      //  Es werden nur maximal 5 Befehle pro Datei verarbeitet!
      break;
    }

    /*********************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  curr_6000 ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    *********************************************************************************/
    if (file_exists( $Pfad."/befehle.ini.php" )) {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 1 );
      $INI_File = parse_ini_file( $Pfad.'/befehle.ini.php', true );
      $Regler44 = $INI_File["Regler44"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler44, 1 ), "|- ", 1 );
      foreach ($Regler44 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
          $funktionen->log_schreiben( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 1 );
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        $funktionen->log_schreiben( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
        $funktionen->log_schreiben( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
        $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
        break;
      }
    }
    else {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
      break;
    }
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    // Hier wird der Befehl gesendet...
    //  $Teile[0] = Befehl
    //  $Teile[1] = Wert
    if (strtolower( $Teile[0] ) == "start") {
      // $sendenachricht = hex2bin("00010000000b0110138800020400000000");  //  Ladung unterbrechen
      // $sendenachricht = hex2bin("0001000000060106138C0000");  //  Ladung einschalten
      // $sendenachricht = hex2bin("000100000006010607D00008");  //  Ladung einschalten
      $sendenachricht = hex2bin( "0001000000060106138C0006" ); //  Ladung einschalten
      $funktionen->log_schreiben( "start senden.", "|- ", 3 );
    }
    if (strtolower( $Teile[0] ) == "amp") {
      $Ampere = floor( $Teile[1] / 1000 );
      $AmpHex = str_pad( dechex( $Ampere ), 4, "0", STR_PAD_LEFT );
      $sendenachricht = hex2bin( "0001000000060106138C".$AmpHex ); //  0003 = 03 = 3 Ampere
      $funktionen->log_schreiben( "Stromänderung senden.", "|- ", 3 );
    }
    if (strtolower( $Teile[0] ) == "setenergy") {
      $Ampere = floor( $Teile[1] );
      $AmpHex = str_pad( dechex( $Ampere ), 8, "0", STR_PAD_LEFT );
      $sendenachricht = hex2bin( "00010000000601061388".$AmpHex ); //  00000003 = 3 Watt
      $funktionen->log_schreiben( "Strommenge Vorgabe senden.", "|- ", 3 );
    }
    $rc = fwrite( $COM1, $sendenachricht );
    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
    $funktionen->log_schreiben( "Antwort: ".$Antwort, "   ", 3 );
    sleep( 2 );
  }
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }
}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}
$i = 1;
do {

  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  // modbus_register_lesen( $COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl )
  //
  ***************************************************************************/
  $funktionen->log_schreiben( "Abfrage: ", "   ", 9 );
  // 0 = aus,  1 = ?,       2 = ?,        3 = wird geladen, 4 = voll geladen
  $rc = $funktionen->modbus_register_lesen( $COM1, "1000", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Ladestatus"] = $rc["Wert"];
  //  0 = keine Ladung,   1 = wird geladen
  $rc = $funktionen->modbus_register_lesen( $COM1, "1001", "0001", "U32", "FF", "03" );
  $aktuelleDaten["LadungAktiv"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1002", "0001", "U32", "FF", "03" );
  $aktuelleDaten["EVSEStatus"] = $rc["Wert"];
  // 0 = kein Kabel angeschlossen, 1 = Kabel an der Ladesäule angeschlossen und
  // verriegelt, 2 = Kabel an beiden Enden verriegelt.
  $rc = $funktionen->modbus_register_lesen( $COM1, "1004", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Kabelstatus"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1006", "0001", "U32", "FF", "03" );
  $aktuelleDaten["ErrorCode"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1008", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Strom_R"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1010", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Strom_S"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1012", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Strom_T"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1100", "0001", "U32", "FF", "03" );
  $aktuelleDaten["HardwareLimit"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1102", "0001", "U32", "FF", "03" );
  $aktuelleDaten["MinLadestrom"] = $rc["Wert"];
  // Ladestrom Vorgabe ist
  $rc = $funktionen->modbus_register_lesen( $COM1, "1104", "0001", "U32", "FF", "03" );
  $aktuelleDaten["AktuelleStromvorgabe"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1106", "0001", "U32", "FF", "03" );
  $aktuelleDaten["KabelLimit"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1020", "0002", "U32", "FF", "03" );
  $aktuelleDaten["Leistung"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1036", "0002", "U32", "FF", "03" );
  $aktuelleDaten["GesamtLeistung"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1502", "0001", "U32", "FF", "03" );
  $aktuelleDaten["LadeLeistung"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "1620", "0001", "U32", "FF", "03" );
  if ($rc) {
    $aktuelleDaten["AutoAngeschlossen"] = $rc["Wert"];
  }
  $rc = $funktionen->modbus_register_lesen( $COM1, "6000", "0001", "U32", "FF", "03" );
  $aktuelleDaten["LifeBit"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "2000", "0001", "U32", "FF", "03" );
  $aktuelleDaten["Test"] = $rc["Wert"];

  $funktionen->log_schreiben( print_r( $rc, 1 ), "*- ", 10 );

  //  print_r($rc);
  //  print_r($aktuelleDaten);

  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Webasto";
  $aktuelleDaten["Firmware"] = "0.0";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy
  if ($i == 1)
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 7 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/webasto_wb_math.php" )) {
    include 'webasto_wb_math.php'; // Falls etwas neu berechnet werden muss.
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
Ausgang:$funktionen->log_schreiben( "-----------------   Stop   webasto_wb.php   --------------------- ", "|--", 6 );
return;
?>
