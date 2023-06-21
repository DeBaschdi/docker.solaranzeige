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
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Reglermodelle = array("0300", "A042", "A043", "A04C", "A053", "A054", "A055", "A05F", "A060");
$Version = "";
$Device = "LR"; // LR = Laderegler
$Start = time( ); // Timestamp festhalten
Log::write( "---------   Start  victron_solarregler.php   ----------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 6 );
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
Log::write( "Hardware Version: ".$Version, "o  ", 8 );
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
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

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
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  if (isset($aktuelleDaten["Error"]) and $i < 3) {
    $i++;
    continue;
  }
  $Befehl = "4"; // Produkt
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  Log::write( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 1 );

  $Befehl = "7400100"; //  Optionen
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7010200"; //  Ladestatus
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D5ED00"; //  Batteriespannung
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D7ED00"; //  Batterie Ladestrom
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7BCED00"; //  Solarleistung
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "74F1000"; //  History  kWh Gesamt
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7BBED00"; //  Solarspannung
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7032000"; //  Batterie Temperatur
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DBED00"; //  Temperatur
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7511000"; //  History  kWh Gestern
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7D2ED00"; //  max Leistung in Watt
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DAED00"; //  Error Codes auslesen
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7DFED00"; //  maxAmpereHeute
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7501000"; //  History  kWh Heute
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  //  LOAD Ausgang Status   Nur bei Ladereglern mit LOAD Ausgang.
  $Befehl = "7A8ED00"; //  Alle anderen haben immer 1
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  if (in_array( $aktuelleDaten["Produkt"], $Reglermodelle )) {
    if ($aktuelleDaten["Produkt"] == "A05F" and $aktuelleDaten["Firmware"] < "1.59" ) {
      // Spezial Regler Modell A05F
      $Befehl = "7BDED00"; //  Batterieladestrom D7ED = alt
      $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
      $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
      $aktuelleDaten["Solarstrom"] = ($aktuelleDaten["Solarstrom"] / 10);
    }
    else {
      // Nur bei den Ladereglern kleiner gleich 15A kein Solarstrom
      $aktuelleDaten["Solarstrom"] = 0;
      $Befehl = "7ADED00"; //  Verbraucherstrom  / Batterieentladestrom
      $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
      if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
        $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
      }
      $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));

    }
  }
  else {
    // Nur bei den Ladereglern größer 15A
    $Befehl = "7BDED00"; //  Batterieladestrom D7ED = alt
    $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
    if ($rc == ":4AAAAFD" or $rc == "") { // Es wird ein Framefehler gemeldet.
      $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));

    $aktuelleDaten["Batterieentladestrom"] = 0;
  }
  if (isset($aktuelleDaten["Framefehler"])) {
    Log::write( "Framefehler! Bitte prüfen.  Exit!", "   ", 1 );
    goto Ausgang;
  }

  /********************
  if ($aktuelleDaten["Batterieladestrom"] > 100  or $aktuelleDaten["Batterieladestrom"] < -100) {
  Log::write("Peak! Bitte prüfen.  Exit!","   ",1);
  Log::write(print_r($aktuelleDaten,1),"   ",7);
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
    $FehlermeldungText = "Fehlermeldung! ".VE::ve_fehlermeldung( $aktuelleDaten["ErrorCodes"] );
    Log::write( $FehlermeldungText, "** ", 1 );
  }
  if ($i == 1)
    Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/victron_solarregler_math.php" )) {
    include $basedir.'/custom/victron_solarregler_math.php'; // Falls etwas neu berechnet werden muss.
  }

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
  $aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

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
    $Zeitspanne = (7 - (time( ) - $Start));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "OK. Daten gelesen.", "   ", 9 );
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
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
Ausgang:Log::write( "---------   Stop   victron_solarregler.php   ----------------- ", "|--", 6 );
return;
?>