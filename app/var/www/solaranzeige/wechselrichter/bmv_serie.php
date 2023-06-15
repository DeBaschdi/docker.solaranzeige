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
//  Es dient dem Auslesen des Victron-energy Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
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
$Tracelevel = 7; //  1 bis 10  10 = Debug
// $Reglermodelle = array("0300","A042","A043","A04C","A053","A054","A055");
$Device = "BMS"; // BMS = Batteriemanagementsystem
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "---------   Start  bmv_serie.php   ----------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
$RemoteDaten = true;
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
$funktionen->log_schreiben( "Hardware Version: ".$Version, "o  ", 9 );
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
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = $funktionen->openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  $funktionen->log_schreiben( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 0;
do {
  $i++;
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batteriestrom"]
  //  $aktuelleDaten["KilowattstundenGesamt"]
  //  $aktuelleDaten["AmperestundenGesamt"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["SOC"]
  //  $aktuelleDaten["TTG"]
  //  $aktuelleDaten["Leistung"]
  //
  ****************************************************************************/
  $Befehl = "1"; // Firmware
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    if ($i < 5) {
      $funktionen->log_schreiben( "Firmware".trim( $rc ), "!!  ", 5 );
      // echo $i."\n";
      continue; // Fehler beim Auslesen aufgetreten. Nochmal...
    }
    else {
      $funktionen->log_schreiben( "Firmware".trim( $rc ), "!!  ", 5 );
      goto Ausgang;
    }
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));

  $Befehl = "4"; // Produkt
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Produkt".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $funktionen->log_schreiben( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 1 );

  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));

  $Befehl = "78DED00"; // Main Voltage   ED8D
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Main Voltage".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FF0F00"; // SOC 0FFF
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "SOC".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "78FED00"; // Current  ED8F
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Optionen".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7100300"; // Cumulative W Hours  Ladung 0310
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "WGesamtLadung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7110300"; // Cumulative W Hours  Entladung 0311
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "WGesamtEntladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7ECED00"; //  Temperatur   EDEC -> in Kelvin! wird umgerechnet in Celsius
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Temperatur".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FE0F00"; // TTG  0FFE
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "TTG".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "78EED00"; // Leistung ED8E
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Leistung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7080300"; // Zeit seit Vollladung 0308
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FFEE00"; // Consumed Energy  Ah  EEFF
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Consumed Energy".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7B6EE00"; // State of Charge EEB6
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "State of Charge".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7020300"; // Depth last discharge    0302
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Depth last discharge".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7030300"; // Number of charge cycles 0303
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Charge cycles".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7040300"; // Number of full discharges 0304
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Full discharges".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7050300"; // Cumulative Amp Hours 0305
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7090300"; // Number of automatic synchronizations 0309
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FCEE00"; // Alarm on/off EEFC
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "74E0300"; // Relais on/off 034E
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "77DED00"; // Aux Voltage   ED7D
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($funktionen->VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    $funktionen->log_schreiben( "Aux Voltage".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));

  if ($aktuelleDaten["TTG"] >= 0) {
    $funktionen->log_schreiben( "Restlaufzeit: ".($aktuelleDaten["TTG"]/60)." Stunden.", "   ", 5 );
  }

  $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  //  User PHP Script, falls gewünscht oder nötig
  /***************************************************************************/
  if (file_exists( "/var/www/html/bmv_serie_math.php" )) {
    include 'bmv_serie_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
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

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  //  Dummy Wert.
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

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
    if ($RemoteDaten) {
      $rc = $funktionen->influx_remote( $aktuelleDaten );
      if ($rc) {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
  }
  elseif ($InfluxDB_local) {
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
    sleep( floor( (55 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "OK. Daten gelesen.", "   ", 8 );
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }

} while (($Start + 55) > time( ));
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
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

Ausgang:

$funktionen->log_schreiben( "---------   Stop   bmv_serie.php   ----------------- ", "|--", 6 );
return;
?>