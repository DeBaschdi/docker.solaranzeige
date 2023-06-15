#!/usr/bin/php
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
//  Es dient dem Auslesen des PAC2200 SmartMeter über die LAN Schnittstelle.
//  MODBUS TCP  Port 502
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
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
$zentralerTimestamp = time( );
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "SM"; // SM = SmartMeter
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "------------------   Start  em24_meter.php    -------------------------- ", "|--", 6 );
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
$StatusFile = $Pfad."/database/".$GeraeteNummer.".Tagesdaten.txt";
$Tagesdaten = array("BezugGesamtHeute" => 0, "EinspeisungGesamtHeute" => 0);
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei ".$StatusFile." nicht anlegen.", 5 );
  }
  $aktuelleDaten["Wh_BezugHeute"] = 0;
  $aktuelleDaten["Wh_EinspeisungHeute"] = 0;
}
else {
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $aktuelleDaten["Wh_BezugHeute"] = $Tagesdaten["BezugGesamtHeute"];
  $aktuelleDaten["Wh_EinspeisungHeute"] = $Tagesdaten["EinspeisungGesamtHeute"];
}
if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( dechex( $WR_Adresse ), 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 2) {
  $WR_ID = str_pad( dechex( substr( $WR_Adresse, - 2 )), 2, "0", STR_PAD_LEFT );
}
else {
  $WR_ID = dechex( $WR_Adresse );
}
$funktionen->log_schreiben( "WR_ID: ".$WR_ID, "+  ", 8 );
$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 ); // 5 = Timeout in Sekunden
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 9 );
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  // function modbus_register_lesen($COM1,$Register,$Laenge,$Typ,$UnitID,$Befehl="03")
  //
  // Auf UnitID und Befehl achten!   UnitID muss 03 sein. Ist hier fest vergeben.
  //  Befehl 03 = single Byte read
  //  UnitID 03 = default
  ****************************************************************************/
  $rc = $funktionen->modbus_register_lesen( $COM1, "0000", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_R"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0002", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_S"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0004", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_T"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0006", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_RS"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0008", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_ST"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0010", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Spannung_RT"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0012", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Strom_R"] = round( $rc["Wert"] / 1000, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0014", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Strom_S"] = round( $rc["Wert"] / 1000, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0016", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Strom_T"] = round( $rc["Wert"] / 1000, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0018", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Leistung_R"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0020", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Leistung_S"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0022", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Leistung_T"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0051", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Frequenz"] = round( $rc["Wert"] / 10, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0040", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["AC_Leistung"] = round( $rc["Wert"] / 10, 3 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0049", "0001", "S16", $WR_ID, "04" );
  $aktuelleDaten["PF_Leistung"] = round( $rc["Wert"] / 1000, 3 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0046", "0001", "S16", $WR_ID, "04" );
  $aktuelleDaten["PF_R"] = round( $rc["Wert"] / 1000, 3 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0047", "0001", "S16", $WR_ID, "04" );
  $aktuelleDaten["PF_S"] = round( $rc["Wert"] / 1000, 3 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0048", "0001", "S16", $WR_ID, "04" );
  $aktuelleDaten["PF_T"] = round( $rc["Wert"] / 1000, 3 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0211", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Tarif"] = 0; // Dummy
  if ($aktuelleDaten["AC_Leistung"] >= 0) {
    $aktuelleDaten["Bezug"] = $aktuelleDaten["AC_Leistung"];
    $aktuelleDaten["Einspeisung"] = 0;
  }
  else {
    $aktuelleDaten["Bezug"] = 0;
    $aktuelleDaten["Einspeisung"] = $aktuelleDaten["AC_Leistung"];
  }
  $rc = $funktionen->modbus_register_lesen( $COM1, "0060", "0002", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["Wh_Bezug"] = round( $rc["Wert"] * 1000, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0078", "0001", "S32-1", $WR_ID, "04" );
  $aktuelleDaten["Wh_Einspeisung"] = round( $rc["Wert"] * 1000, 2 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0961", "0004", "F64", $WR_ID, "04" );
  $aktuelleDaten["GesamterLeistungsbedarf"] = ($aktuelleDaten["Wh_Bezug"] + $aktuelleDaten["Wh_Einspeisung"]); // Dummy
  $rc = $funktionen->modbus_register_lesen( $COM1, "0011", "0001", "Hex", $WR_ID, "04" );
  $aktuelleDaten["HerstellerID"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen( $COM1, "20480", "0007", "", $WR_ID, "04" );
  $aktuelleDaten["Seriennummer"] = $funktionen->Hex2String( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0770", "0001", "", $WR_ID, "04" );
  $aktuelleDaten["Firmware"] = substr( $rc["Wert"], 0, 1 ).".".substr( $rc["Wert"], 1, 1 ).".".hexdec( substr( $rc["Wert"], 2, 2 ));

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["HerstellerID"];
  $aktuelleDaten["Modell"] = "EM24";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // Dummy
  $aktuelleDaten["AC_Strom"] = $aktuelleDaten["AC_Strom_R"] + $aktuelleDaten["AC_Strom_S"] + $aktuelleDaten["AC_Strom_T"];
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/em24_meter_math.php" )) {
    include 'em24_meter_math.php'; // Falls etwas neu berechnet werden muss.
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
if (isset($aktuelleDaten["Firmware"])) {

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
  $Tagesdaten["BezugGesamtHeute"] = round( ($Tagesdaten["BezugGesamtHeute"] + ($aktuelleDaten["Bezug"]) / 60), 2 );
  $Tagesdaten["EinspeisungGesamtHeute"] = round( ($Tagesdaten["EinspeisungGesamtHeute"] + ($aktuelleDaten["Einspeisung"]) / 60), 2 );
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  $funktionen->log_schreiben( "BezugGesamtHeute: ".$Tagesdaten["BezugGesamtHeute"], "   ", 5 );
  $funktionen->log_schreiben( "EinspeisungGesamtHeute: ".$Tagesdaten["EinspeisungGesamtHeute"], "   ", 5 );
}
Ausgang:fclose( $COM1 );
$funktionen->log_schreiben( "------------------   Stop   em24_meter.php    -------------------------- ", "|--", 6 );
return;
?>

