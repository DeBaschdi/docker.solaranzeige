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
//  Es dient dem Auslesen des SMARTPI Zählers über das LAN.
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
$Device = "SM"; // SM = SmartMeter
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  smartpi.php   --------------------- ", "|--", 6 );
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
//  Die Status Datei wird dazu benutzt, um mehrere Variablen des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad . "/database/" . $GeraeteNummer . ".Tagesdaten.txt";
$Tagesdaten = array("BezugGesamtHeute" => 0, "EinspeisungGesamtHeute" => 0);
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei " . $StatusFile . " nicht anlegen.", 5 );
  }
  $aktuelleDaten["Wh_Bezug"] = 0;
  $aktuelleDaten["Wh_Einspeisung"] = 0;
}
else {
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $aktuelleDaten["Wh_Bezug"] = $Tagesdaten["BezugGesamtHeute"];
  $aktuelleDaten["Wh_Einspeisung"] = $Tagesdaten["EinspeisungGesamtHeute"];
}



if (empty($WR_Adresse)) {
  $WBID = "1";
}
else {
  $WBID = (intval($WR_Adresse));
}

$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ****************************************************************************/
  $URL = "api/all/power/now";
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


  $aktuelleDaten["Name"] = $Daten["name"];
  $aktuelleDaten["Seriennummer"] = $Daten["serial"];
  $aktuelleDaten["Zeitpunkt"] = $Daten["time"];
  $aktuelleDaten["Breite"] = $Daten["lat"];
  $aktuelleDaten["Laenge"] = $Daten["lng"];
  $aktuelleDaten["Protokoll-Version"] = $Daten["softwareversion"];
  $aktuelleDaten["AC_Leistung_R"] = $Daten["datasets"]["0"]["phases"]["0"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Leistung_S"] = $Daten["datasets"]["0"]["phases"]["1"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Leistung_T"] = $Daten["datasets"]["0"]["phases"]["2"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Leistung_N"] = $Daten["datasets"]["0"]["phases"]["3"]["values"]["0"]["data"];



  $URL = "api/all/current/now";
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

  $aktuelleDaten["AC_Strom_R"] = $Daten["datasets"]["0"]["phases"]["0"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Strom_S"] = $Daten["datasets"]["0"]["phases"]["1"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Strom_T"] = $Daten["datasets"]["0"]["phases"]["2"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Strom_N"] = $Daten["datasets"]["0"]["phases"]["3"]["values"]["0"]["data"];


  $URL = "api/all/voltage/now";
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

  $aktuelleDaten["AC_Spannung_R"] = $Daten["datasets"]["0"]["phases"]["0"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Spannung_S"] = $Daten["datasets"]["0"]["phases"]["1"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Spannung_T"] = $Daten["datasets"]["0"]["phases"]["2"]["values"]["0"]["data"];
  $aktuelleDaten["AC_Spannung_N"] = $Daten["datasets"]["0"]["phases"]["3"]["values"]["0"]["data"];

  $URL = "api/all/cosphi/now";
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

  $aktuelleDaten["PF_R"] = $Daten["datasets"]["0"]["phases"]["0"]["values"]["0"]["data"];
  $aktuelleDaten["PF_S"] = $Daten["datasets"]["0"]["phases"]["1"]["values"]["0"]["data"];
  $aktuelleDaten["PF_T"] = $Daten["datasets"]["0"]["phases"]["2"]["values"]["0"]["data"];
  $aktuelleDaten["PF_N"] = $Daten["datasets"]["0"]["phases"]["3"]["values"]["0"]["data"];


  $URL = "api/all/frequency/now";
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

  $aktuelleDaten["Frequenz_R"] = $Daten["datasets"]["0"]["phases"]["0"]["values"]["0"]["data"];
  $aktuelleDaten["Frequenz_S"] = $Daten["datasets"]["0"]["phases"]["1"]["values"]["0"]["data"];
  $aktuelleDaten["Frequenz_T"] = $Daten["datasets"]["0"]["phases"]["2"]["values"]["0"]["data"];
  $aktuelleDaten["Frequenz_N"] = $Daten["datasets"]["0"]["phases"]["3"]["values"]["0"]["data"];


  $aktuelleDaten["Frequenz"] = $aktuelleDaten["Frequenz_R"];
  $aktuelleDaten["AC_Strom"] = ($aktuelleDaten["AC_Strom_R"] + $aktuelleDaten["AC_Strom_S"] + $aktuelleDaten["AC_Strom_T"]) ;
  $aktuelleDaten["AC_Leistung"] = ($aktuelleDaten["AC_Leistung_R"] + $aktuelleDaten["AC_Leistung_S"] + $aktuelleDaten["AC_Leistung_T"]);

  if ($aktuelleDaten["AC_Leistung"] > 0) {
    $aktuelleDaten["Bezug"] = $aktuelleDaten["AC_Leistung"];
    $aktuelleDaten["Einspeisung"] = 0;
  }
  else {
    $aktuelleDaten["Einspeisung"] = abs($aktuelleDaten["AC_Leistung"]);
    $aktuelleDaten["Bezug"] = 0;
  }


  $aktuelleDaten["GesamterLeistungsbedarf"]  = ($aktuelleDaten["Wh_Bezug"] + $aktuelleDaten["Wh_Einspeisung"]);
  $aktuelleDaten["PF_Leistung"]  = $aktuelleDaten["PF_R"];

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
  $aktuelleDaten["Modell"] = "SmartPi";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  if ($i == 1)
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/smartpi_math.php" )) {
    include 'smartpi_math.php'; // Falls etwas neu berechnet werden muss.
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
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
  //  pro Tag zu speichern.
  //  Jede Nacht 0 Uhr Tageszähler auf 0 setzen
  ***************************************************************************/
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") {
    $Tagesdaten = array("BezugGesamtHeute" => 0, "EinspeisungGesamtHeute" => 0);
    $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
    $funktionen->log_schreiben( "BezugGesamtHeute  gesetzt.", "o- ", 5 );
  }

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $Tagesdaten["BezugGesamtHeute"] = ($Tagesdaten["BezugGesamtHeute"] + ($aktuelleDaten["Bezug"]) / 60);
  $Tagesdaten["EinspeisungGesamtHeute"] = ($Tagesdaten["EinspeisungGesamtHeute"] + ($aktuelleDaten["Einspeisung"]) / 60);
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  $funktionen->log_schreiben( "BezugGesamtHeute: " . round( $Tagesdaten["BezugGesamtHeute"], 2 ), "   ", 5 );
  $funktionen->log_schreiben( "EinspeisungGesamtHeute: " . round( $Tagesdaten["EinspeisungGesamtHeute"], 2 ), "   ", 5 );
}


Ausgang:$funktionen->log_schreiben( "-------------   Stop   smartpi.php   ---------------------- ", "|--", 6 );
return;
?>
