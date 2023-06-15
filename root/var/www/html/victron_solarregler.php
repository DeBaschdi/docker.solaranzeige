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
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Reglermodelle = array("0300", "A042", "A043", "A04C", "A053", "A054", "A055", "A05F", "A060");
$Version = "";
$Device = "LR"; // LR = Laderegler
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "---------   Start  victron_solarregler.php   ----------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 6 );
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
$funktionen->log_schreiben( "Hardware Version: ".$Version, "o  ", 8 );
switch ($Version) {

  case "2B":
    break;

  case "3B":
    break;

  case "3BPlus":
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
$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["KilowattstundenGesamt"]
  //  $aktuelleDaten["KilowattstundenGesamtHeute"]
  //  $aktuelleDaten["KilowattstundenGesamtGestern"]
  //  $aktuelleDaten["maxWattHeute"]
  //  $aktuelleDaten["maxAmpHeute"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["Optionen"]
  //  $aktuelleDaten["ErrorCodes"]
  //
  ****************************************************************************/

  $Befehl = "1"; // Firmware
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  if (isset($aktuelleDaten["Error"]) and $i < 3) {
    $i++;
    continue;
  }
  $Befehl = "4"; // Produkt
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $funktionen->log_schreiben( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 1 );

  $Befehl = "7400100"; //  Optionen
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7010200"; //  Ladestatus
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D5ED00"; //  Batteriespannung
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D7ED00"; //  Batterie Ladestrom
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7BCED00"; //  Solarleistung
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "74F1000"; //  History  kWh Gesamt
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7BBED00"; //  Solarspannung
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7032000"; //  Batterie Temperatur
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DBED00"; //  Temperatur
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7511000"; //  History  kWh Gestern
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D2ED00"; //  max Leistung in Watt
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DAED00"; //  Error Codes auslesen
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DFED00"; //  maxAmpereHeute
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  $Befehl = "7501000"; //  History  kWh Heute
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  //  LOAD Ausgang Status   Nur bei Ladereglern mit LOAD Ausgang.
  $Befehl = "7A8ED00"; //  Alle anderen haben immer 1
  $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
  if (in_array( $aktuelleDaten["Produkt"], $Reglermodelle )) {
    if ($aktuelleDaten["Produkt"] == "A05F" and $aktuelleDaten["Firmware"] < "1.59" ) {
      // Spezial Regler Modell A05F
      $Befehl = "7BDED00"; //  Batterieladestrom D7ED = alt
      $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
      $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));
      $aktuelleDaten["Solarstrom"] = ($aktuelleDaten["Solarstrom"] / 10);
    }
    else {
      // Nur bei den Ladereglern kleiner gleich 15A kein Solarstrom
      $aktuelleDaten["Solarstrom"] = 0;
      $Befehl = "7ADED00"; //  Verbraucherstrom  / Batterieentladestrom
      $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
      if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
        $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
      }
      $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));

    }
  }
  else {
    // Nur bei den Ladereglern größer 15A
    $Befehl = "7BDED00"; //  Batterieladestrom D7ED = alt
    $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
    if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
      $rc = $funktionen->ve_regler_auslesen( $USB1, ":".$Befehl.$funktionen->VE_CRC( $Befehl ));
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, $funktionen->ve_ergebnis_auswerten( $rc ));

    $aktuelleDaten["Batterieentladestrom"] = 0;
  }
  if (isset($aktuelleDaten["Framefehler"])) {
    $funktionen->log_schreiben( "Framefehler! Bitte prüfen.  Exit!", "   ", 1 );
    goto Ausgang;
  }

  /********************
  if ($aktuelleDaten["Batterieladestrom"] > 100  or $aktuelleDaten["Batterieladestrom"] < -100) {
  $funktionen->log_schreiben("Peak! Bitte prüfen.  Exit!","   ",1);
  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"   ",7);
  exit;
  }
  *********************/

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  if (!is_numeric($aktuelleDaten["Firmware"])) {
    $aktuelleDaten["Firmware"] = 0;
  }
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  if ($aktuelleDaten["Ladestatus"] == 2) {
    $FehlermeldungText = "Fehlermeldung! ".$funktionen->ve_fehlermeldung( $aktuelleDaten["ErrorCodes"] );
    $funktionen->log_schreiben( $FehlermeldungText, "** ", 1 );
  }
  if ($i == 1)
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/victron_solarregler_math.php" )) {
    include 'victron_solarregler_math.php'; // Falls etwas neu berechnet werden muss.
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
  $aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

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
    $funktionen->log_schreiben( "OK. Daten gelesen.", "   ", 9 );
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 52) > time( ));
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
Ausgang:$funktionen->log_schreiben( "---------   Stop   victron_solarregler.php   ----------------- ", "|--", 6 );
return;
?>